<?php

namespace wooo\lib\orm;

class FO
{
    
    public const EQ = 'eq';
    
    public const NE = 'ne';
    
    public const LT = 'lt';
    
    public const GT = 'gt';
    
    public const LE = 'le';
    
    public const GE = 'ge';
    
    public const BETWEEN = 'bw';
    
    public const IN = 'in';
    
    public const AND = 'and';
    
    public const OR = 'or';
    
    public const NOT = 'not';
    
    public const ISNULL = 'isnull';
    
    public const ADD = 'add';
    
    public const SUB = 'sub';
    
    public const MUL = 'mul';
    
    public const DIV = 'div';
    
    public const MOD = 'mod';
    
    public const BW_AND = 'band';
    
    public const BW_OR = 'bor';
    
    public const BW_XOR = 'bxor';
    
    public const NVL = 'nvl';
    
    public const LIKE = 'like';
    
    public static function l()
    {
        return [
            self::EQ,
            self::NE,
            self::LT,
            self::GT,
            self::LE,
            self::GE,
            self::BETWEEN,
            self::IN,
            self::AND,
            self::OR,
            self::NOT,
            self::ISNULL,
            self::ADD,
            self::SUB,
            self::MUL,
            self::DIV,
            self::MOD,
            self::BW_AND,
            self::BW_OR,
            self::BW_XOR,
            self::NVL,
            self::LIKE
        ];
    }
}
