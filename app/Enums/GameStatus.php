<?php

namespace App\Enums;

enum GameStatus: string
{
    case Draft = 'draft';
    case Running = 'running';
    case Finished = 'finished';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
