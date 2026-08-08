<?php

namespace App\Enums;

enum PhaseStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
