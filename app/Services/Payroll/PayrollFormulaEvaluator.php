<?php

namespace App\Services\Payroll;

use InvalidArgumentException;

class PayrollFormulaEvaluator
{
    private array $tokens = [];

    private int $position = 0;

    public function evaluate(string $formula, array $values): float
    {
        preg_match_all('/[a-z_]+|(?:\d+(?:\.\d*)?|\.\d+)|[()+\-*\/]/i', $formula, $matches);
        $tokens = $matches[0];
        $joined = preg_replace('/\s+/', '', $formula);

        if ($tokens === [] || implode('', $tokens) !== $joined) {
            throw new InvalidArgumentException('La fórmula contiene caracteres no permitidos.');
        }

        $this->tokens = $tokens;
        $this->position = 0;
        $result = $this->parseExpression($values);

        if ($this->position !== count($this->tokens)) {
            throw new InvalidArgumentException('La fórmula no tiene una estructura válida.');
        }

        return round($result, 2);
    }

    private function parseExpression(array $values): float
    {
        $result = $this->parseTerm($values);
        while (in_array($this->peek(), ['+', '-'], true)) {
            $operator = $this->tokens[$this->position++];
            $operand = $this->parseTerm($values);
            $result = $operator === '+' ? $result + $operand : $result - $operand;
        }

        return $result;
    }

    private function parseTerm(array $values): float
    {
        $result = $this->parseFactor($values);
        while (in_array($this->peek(), ['*', '/'], true)) {
            $operator = $this->tokens[$this->position++];
            $operand = $this->parseFactor($values);
            if ($operator === '/' && $operand == 0.0) {
                throw new InvalidArgumentException('La fórmula no puede dividir entre cero.');
            }
            $result = $operator === '*' ? $result * $operand : $result / $operand;
        }

        return $result;
    }

    private function parseFactor(array $values): float
    {
        $token = $this->tokens[$this->position++] ?? null;
        if ($token === null) {
            throw new InvalidArgumentException('La fórmula está incompleta.');
        }

        if ($token === '-') {
            return -$this->parseFactor($values);
        }
        if ($token === '+') {
            return $this->parseFactor($values);
        }
        if ($token === '(') {
            $result = $this->parseExpression($values);
            if (($this->tokens[$this->position++] ?? null) !== ')') {
                throw new InvalidArgumentException('Falta cerrar un paréntesis en la fórmula.');
            }

            return $result;
        }
        if (is_numeric($token)) {
            return (float) $token;
        }
        if (array_key_exists(strtolower($token), $values)) {
            return (float) $values[strtolower($token)];
        }

        throw new InvalidArgumentException('Variable no permitida en la fórmula: '.$token);
    }

    private function peek(): ?string
    {
        return $this->tokens[$this->position] ?? null;
    }
}
