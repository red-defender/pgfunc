<?php
namespace PgFunc {
    use PgFunc\Exception\Usage;
    use PgFunc\OptionTrait\AttemptsCount;
    use PgFunc\OptionTrait\JsonResult;
    use PgFunc\OptionTrait\LocalParams;

    /**
     * Class for defining stored procedure.
     *
     * @author red-defender
     * @package pgfunc
     */
    class Procedure {
        use AttemptsCount, JsonResult, LocalParams;

        /**
         * Possible return types of the procedure.
         */
        const RETURN_VOID   = 'VOID';
        const RETURN_SIMPLE = 'SIMPLE';
        const RETURN_RECORD = 'RECORD';
        const RETURN_ARRAY  = 'ARRAY';

        /**
         * Alias for result field.
         */
        const RESULT_FIELD = 'result';

        /**
         * @var string Procedure name (may contain a schema name).
         */
        protected $name = '';

        /**
         * @var array Parameters definition array.
         */
        protected $parameters = [];

        /**
         * @var bool[] Optional parameters flags.
         */
        protected $optionals = [];

        /**
         * @var string|null VARIADIC parameter name.
         */
        protected $variadic;

        /**
         * @var string Current return type.
         */
        protected $returnType = self::RETURN_VOID;

        /**
         * @var bool Result set always contains one row.
         */
        protected $isSingleRow = false;

        /**
         * @var callable|null Callback for identifying rows of result set.
         */
        protected $resultIdentifierCallback;

        /**
         * @var string[] Array of known error messages.
         */
        protected $errorMap = [];

        /**
         * @var array Current parameters values.
         */
        protected $data = [];

        /**
         * @var bool SQL query string can be cached.
         */
        protected $isCacheable = true;

        /**
         * @var string|null Cached SQL query string.
         */
        protected $sqlCache;

        /**
         * Constructor method may be overridden to pass name check.
         *
         * @param string $name Procedure name (optionally schema-qualified).
         */
        public function __construct($name) {
            $this->name = $this->checkIdentifier($name, 'Invalid procedure name: ' . $name, true);
        }

        /**
         * Add parameter definition.
         *
         * @param string $name Parameter name.
         * @param mixed $definition Parameter definition.
         * @param bool $isOptional Flag for optional parameter.
         * @param bool $isVariadic Flag for VARIADIC parameter.
         * @return self
         * @throws Usage When definition is invalid.
         */
        final public function addParameter($name, $definition, $isOptional = false, $isVariadic = false) {
            $name = $this->checkIdentifier($name, 'Invalid parameter name: ' . $name);
            if (isset($this->parameters[$name])) {
                throw new Usage('Parameter is already defined: ' . $name, Exception::INVALID_DEFINITION);
            }

            $definition = $this->checkDefinition($definition, $name);

            if ($isVariadic) {
                if ($this->variadic) {
                    throw new Usage(
                        'Unable to add another VARIADIC parameter: ' . $name,
                        Exception::INVALID_DEFINITION
                    );
                }
                if (! is_array($definition) || array_keys($definition) !== [0]) {
                    throw new Usage('VARIADIC parameter is not an array: ' . $name, Exception::INVALID_DEFINITION);
                }

                $this->variadic = $name;
                $this->isCacheable = false;
            }

            if (is_array($definition) || ($definition instanceof SpecialType && ! $definition->isCacheable())) {
                $this->isCacheable = false;
            }

            $this->parameters[$name] = $definition;
            $this->sqlCache = null;
            if ($isOptional) {
                $this->optionals[$name] = true;
                $this->isCacheable = false;
            }
            return $this;
        }

        /**
         * @param string $returnType Current return type (see self::RETURN_* constants).
         * @return self
         * @throws Usage When return type is unknown.
         */
        final public function setReturnType($returnType) {
            $returnTypes = [
                self::RETURN_VOID,
                self::RETURN_SIMPLE,
                self::RETURN_RECORD,
                self::RETURN_ARRAY,
            ];
            if (! in_array($returnType, $returnTypes, true)) {
                throw new Usage('Unknown return type: ' . $returnType, Exception::INVALID_RETURN_TYPE);
            }
            $this->returnType = $returnType;
            $this->sqlCache = null;
            return $this;
        }

        /**
         * @return string Current return type.
         */
        final public function getReturnType() {
            return $this->returnType;
        }

        /**
         * @param bool $isSingleRow Result set always contains one row.
         * @return self
         */
        final public function setIsSingleRow($isSingleRow) {
            $this->isSingleRow = (bool) $isSingleRow;
            return $this;
        }

        /**
         * @return bool Result set always contains one row.
         */
        final public function isSingleRow() {
            return $this->isSingleRow;
        }

        /**
         * @param callable $resultIdentifierCallback Callback for identifying rows of result set.
         * @return self
         */
        final public function setResultIdentifierCallback(callable $resultIdentifierCallback) {
            $this->resultIdentifierCallback = $resultIdentifierCallback;
            return $this;
        }

        /**
         * @return callable|null Callback for identify rows of result set.
         */
        final public function getResultIdentifierCallback() {
            return $this->resultIdentifierCallback;
        }

        /**
         * @param string[] $errorMap Array of known error messages (keys transform to lowercase).
         * @return self
         */
        final public function setErrorMap(array $errorMap) {
            $this->errorMap = [];
            foreach ($errorMap as $key => $code) {
                $this->errorMap[strtolower($key)] = $code;
            }
            return $this;
        }

        /**
         * Recognizing error cause.
         *
         * @param string $exceptionMessage Database exception message.
         * @return string|null Error code or exception class name when error is known or null otherwise.
         */
        final public function handleError($exceptionMessage) {
            $exceptionMessage = strtolower($exceptionMessage);
            foreach ($this->errorMap as $key => $code) {
                if (strpos($exceptionMessage, $key) !== false) {
                    return $code;
                }
            }
            return null;
        }

        /**
         * Set actual parameter value.
         *
         * @param string $name Parameter name.
         * @param mixed $data Parameter value.
         * @return self
         * @throws Usage When value is invalid.
         */
        final public function setData($name, $data) {
            $name = $this->checkIdentifier($name, 'Invalid parameter name: ' . $name);
            if (empty($this->parameters[$name])) {
                throw new Usage('Unknown parameter: ' . $name, Exception::INVALID_DATA);
            }
            $this->data[$name] = $this->checkData($this->parameters[$name], $data, $name);
            return $this;
        }

        /**
         * Clear all parameters values.
         *
         * @return self
         */
        final public function clearData() {
            $this->data = [];
            return $this;
        }

        /**
         * Generate SQL query string and parameters array for binding.
         *
         * @return array Array of query string and parameters array.
         * @throws Usage When required parameters are missing.
         */
        final public function generateQueryData() {
            foreach (array_keys($this->parameters) as $name) {
                if (! array_key_exists($name, $this->data) && empty($this->optionals[$name])) {
                    throw new Usage('Required parameter is missing: ' . $name, Exception::INVALID_DATA);
                }
            }
            return [$this->generateSql(), $this->generateParameters()];
        }

        /**
         * Check database object identifier and turn it into quoted form.
         *
         * @param string $identifier Database object name.
         * @param string $message Error message for exception.
         * @param bool $isQualified Schema-qualified name.
         * @return string Checked name.
         * @throws Usage When identifier is invalid.
         */
        private function checkIdentifier($identifier, $message, $isQualified = false) {
            $pattern = '([a-z_][a-z0-9_\$]*|"(?:[^"\x00]|"")+")';
            if ($isQualified) {
                $pattern = '(?:' . $pattern . '\s*\.\s*)?' . $pattern;
            }
            if (! preg_match('/^' . $pattern . '$/isDS', $identifier, $parts)) {
                throw new Usage($message, Exception::INVALID_IDENTIFIER);
            }

            unset($parts[0]);
            $parts = array_map(
                function ($part) {
                    return ($part[0] === '"') ? $part : '"' . strtolower($part) . '"';
                },
                array_filter($parts)
            );
            return implode('.', $parts);
        }

        /**
         * Check parameter definition.
         *
         * Recursive method.
         *
         * @param string|array|SpecialType $definition Parameter definition.
         * @param string $keyPath Current definition prefix in nested types.
         * @return mixed Checked definition.
         * @throws Usage When definition is invalid.
         */
        private function checkDefinition($definition, $keyPath) {
            // Simple type.
            if (is_string($definition)) {
                return $this->checkIdentifier(
                    $definition,
                    'Invalid definition of ' . $keyPath . ' parameter: ' . $definition,
                    true
                );
            } elseif (is_array($definition)) {
                // Array.
                if (array_keys($definition) === [0]) {
                    $definition[0] = $this->checkDefinition($definition[0], $keyPath . '/0');
                    return $definition;
                }

                // Record.
                if (empty($definition[Mapper::RECORD_TYPE])) {
                    throw new Usage('Record type is missing: ' . $keyPath, Exception::INVALID_DEFINITION);
                }
                $newDefinition[Mapper::RECORD_TYPE] = $this->checkIdentifier(
                    $definition[Mapper::RECORD_TYPE],
                    'Invalid type of ' . $keyPath . ' record: ' . $definition[Mapper::RECORD_TYPE],
                    true
                );

                // Checking fields of record.
                unset($definition[Mapper::RECORD_TYPE]);
                if (! $definition) {
                    throw new Usage('Record does not contain any fields: ' . $keyPath, Exception::INVALID_DEFINITION);
                }
                foreach ($definition as $name => $type) {
                    $name = $this->checkIdentifier($name, 'Invalid name of ' . $keyPath . ' record field: ' . $name);
                    if (isset($newDefinition[$name])) {
                        throw new Usage(
                            'Field of ' . $keyPath . ' record already exists: ' . $name,
                            Exception::INVALID_DEFINITION
                        );
                    }
                    $newDefinition[$name] = $this->checkDefinition($type, $keyPath . '/' . $name);
                }
                return $newDefinition;
            } elseif ($definition instanceof SpecialType) {
                return $definition;
            }

            throw new Usage(
                'Invalid definition type of ' . $keyPath . ' parameter: ' . gettype($definition),
                Exception::INVALID_DEFINITION
            );
        }

        /**
         * Check parameters value.
         *
         * Recursive method.
         *
         * @param array|string|SpecialType $definition Parameter definition.
         * @param mixed $data Parameter value.
         * @param string $keyPath Current value prefix in nested types.
         * @return mixed Checked value.
         * @throws Usage When parameter value is invalid.
         */
        private function checkData($definition, $data, $keyPath) {
            // NULL value is always accepted.
            if (is_null($data)) {
                return null;
            }

            // Special type.
            if ($definition instanceof SpecialType) {
                $definition->checkData($data, $keyPath);
                return $data;
            }

            // Scalar value.
            if (is_string($definition)) {
                if (! is_scalar($data)) {
                    throw new Usage(
                        'Value of ' . $keyPath . ' parameter with ' . $definition . ' type is not scalar',
                        Exception::INVALID_DATA
                    );
                }
                return $data;
            }

            // Array.
            if (! is_array($data)) {
                throw new Usage('Parameter value is not an array: ' . $keyPath, Exception::INVALID_DATA);
            }
            if (isset($definition[0])) {
                $index = 0;
                foreach ($data as $name => $value) {
                    $data[$name] = $this->checkData($definition[0], $value, $keyPath . '/' . $index);
                    $index++;
                }
                return $data;
            }

            // Record.
            unset($definition[Mapper::RECORD_TYPE]);
            $newData = [];
            foreach ($data as $name => $value) {
                $name = $this->checkIdentifier($name, 'Invalid name of ' . $keyPath . ' record field: ' . $name);
                if (! isset($definition[$name])) {
                    throw new Usage('Unknown field of ' . $keyPath . ' record: ' . $name, Exception::INVALID_DATA);
                }
                $newData[$name] = $this->checkData($definition[$name], $value, $keyPath . '/' . $name);
            }
            if (count($newData) !== count($definition)) {
                throw new Usage('Wrong field count in record value: ' . $keyPath, Exception::INVALID_DATA);
            }

            // Ordering record fields.
            return array_replace($definition, $newData);
        }

        /**
         * Generate full SQL query string with placeholders.
         *
         * @return string
         */
        private function generateSql() {
            if ($this->sqlCache) {
                return $this->sqlCache;
            }
            $sql = $this->name . '(' . $this->generateSqlParameters() . ')';
            switch ($this->returnType) {
                case self::RETURN_SIMPLE:
                    $sql = 'TO_JSON(' . $sql . ') AS ' . self::RESULT_FIELD;
                    break;
                case self::RETURN_RECORD:
                    $sql = 'ROW_TO_JSON(' . $sql . ') AS ' . self::RESULT_FIELD;
                    break;
                case self::RETURN_ARRAY:
                    $sql = 'ARRAY_TO_JSON(' . $sql . ') AS ' . self::RESULT_FIELD;
                    break;
            }
            $sql = 'SELECT ' . $sql;
            if ($this->isCacheable) {
                $this->sqlCache = $sql;
            }
            return $sql;
        }

        /**
         * Generate SQL code for parameters placeholders.
         *
         * @return string
         */
        private function generateSqlParameters() {
            $sqlParts = [];
            $index = 0;
            foreach ($this->data as $name => $value) {
                $sql = $this->generateSqlValue($value, $this->parameters[$name], 'p' . $index, true);
                $sql = $name . ':=' . $sql;
                if ($name === $this->variadic) {
                    $sql = 'VARIADIC ' . $sql;
                }
                $sqlParts[] = $sql;
                $index++;
            }
            return implode(',', $sqlParts);
        }

        /**
         * Generate parameters placeholders.
         *
         * Recursive method.
         *
         * @param mixed $value Parameter value.
         * @param array|string|SpecialType $definition Parameter definition.
         * @param string $prefix Placeholder prefix.
         * @param bool $isType Add SQL type name to placeholder.
         * @return string SQL placeholder string.
         */
        private function generateSqlValue($value, $definition, $prefix, $isType = false) {
            // Special NULL or scalar value.
            if (is_null($value) || is_string($definition)) {
                return ':' . $prefix . ($isType ? '::' . $this->generateSqlType($definition) : '');
            }

            // Special type.
            if ($definition instanceof SpecialType) {
                return $definition->getSql($value, $prefix);
            }

            // Array.
            if (array_keys($definition) === [0]) {
                $index = 0;
                $array = [];
                foreach ($value as $item) {
                    $array[] = $this->generateSqlValue($item, $definition[0], $prefix . 'e' . $index);
                    $index++;
                }
                return 'ARRAY[' . implode(',', $array) . ']::' . $this->generateSqlType($definition);
            }

            // Record.
            $index = 0;
            $record = [];
            foreach ($value as $name => $item) {
                $record[] = $this->generateSqlValue($item, $definition[$name], $prefix . 'f' . $index);
                $index++;
            }
            return 'ROW(' . implode(',', $record) . ')' . ($isType ? '::' . $definition[Mapper::RECORD_TYPE] : '');
        }

        /**
         * Generate SQL type name.
         *
         * Recursive method.
         *
         * @param array|string|SpecialType $definition Parameter definition.
         * @return string SQL string with full type name.
         */
        private function generateSqlType($definition) {
            if ($definition instanceof SpecialType) {
                return $definition->getTypeName();
            } elseif (is_string($definition)) {
                return $definition;
            } elseif (array_keys($definition) === [0]) {
                return $this->generateSqlType($definition[0]) . '[]';
            } else {
                return $definition[Mapper::RECORD_TYPE];
            }
        }

        /**
         * Generate array of placeholders values.
         *
         * @return array
         */
        private function generateParameters() {
            $params = [];
            $index = 0;
            foreach ($this->data as $name => $value) {
                $params += $this->generateParameterValue($value, $this->parameters[$name], 'p' . $index);
                $index++;
            }
            return $params;
        }

        /**
         * Generate array of parameter placeholders values.
         *
         * Recursive method.
         *
         * @param mixed $value Parameter value.
         * @param array|string|SpecialType $definition Parameter definition.
         * @param string $prefix Placeholder prefix.
         * @return array Values array.
         */
        private function generateParameterValue($value, $definition, $prefix) {
            // Special NULL value.
            if (is_null($value)) {
                return [':' . $prefix => $value];
            }

            // Special type.
            if ($definition instanceof SpecialType) {
                return $definition->getParameter($value, $prefix);
            }

            // Scalar value or resource.
            if (is_scalar($value) || is_resource($value)) {
                return [':' . $prefix => $value];
            }

            // Array.
            if (array_keys($definition) === [0]) {
                $index = 0;
                $array = [];
                foreach ($value as $item) {
                    $array += $this->generateParameterValue($item, $definition[0], $prefix . 'e' . $index);
                    $index++;
                }
                return $array;
            }

            // Record.
            $index = 0;
            $record = [];
            foreach ($value as $name => $item) {
                $record += $this->generateParameterValue($item, $definition[$name], $prefix . 'f' . $index);
                $index++;
            }
            return $record;
        }
    }
}
