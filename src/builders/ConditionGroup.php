<?php

/*
 *
 *    _______   _______    _______   _______
 *   /       \\/       \\//       \//       \
 *  /        //        ///        //      __/
 * /         /         /        _/        _/
 * \__/__/__/\________/\____/___/\_______/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author CSSM Group
 * @link https://cssm.pp.ua/
 *
 *
 */

declare(strict_types=1);

namespace morfeditorial\builders;

class ConditionGroup
{
    const TYPE_AND = 'AND';

    const TYPE_OR = 'OR';

    private array $conditions = [];

    private string $boolean;

    public function __construct(string $boolean = self::TYPE_AND)
    {
        $this->boolean = $boolean;
    }

    public function addCondition(string $column, string $operator, $value, string $boolean) : void
    {
        $this->conditions[] = [
            'type' => 'condition',
            'boolean' => $boolean,
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];
    }

    public function addGroup(ConditionGroup $group, string $boolean) : void
    {
        $this->conditions[] = [
            'type' => 'group',
            'boolean' => $boolean,
            'group' => $group,
        ];
    }

    public function getBoolean() : string
    {
        return $this->boolean;
    }

    public function getConditions() : array
    {
        return $this->conditions;
    }

    public function isEmpty() : bool
    {
        return empty($this->conditions);
    }
}
