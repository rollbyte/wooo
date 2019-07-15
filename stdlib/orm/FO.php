<?php

namespace wooo\stdlib\orm;

class FO
{
    
    const EQ = 'eq';
    
    const NE = 'ne';
    
    const LT = 'lt';
    
    const GT = 'gt';
    
    const LE = 'le';
    
    const GE = 'ge';
    
    const BETWEEN = 'bw';
    
    const IN = 'in';
    
    const AND = 'and';
    
    const OR = 'or';
    
    const NOT = 'not';
    
    const ISNULL = 'isnull';
    
    const ADD = 'add';
    
    const SUB = 'sub';
    
    const MUL = 'mul';
    
    const DIV = 'div';
    
    const MOD = 'mod';
    
    const BW_AND = 'band';
    
    const BW_OR = 'bor';
    
    const BW_XOR = 'bxor';
    
    const NVL = 'nvl';
    
    const LIKE = 'like';
    
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
