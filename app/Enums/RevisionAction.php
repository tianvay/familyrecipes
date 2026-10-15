<?php

namespace App\Enums;

enum RevisionAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    /**
     * Verb für Sätze wie „Anna hat das Rezept bearbeitet“.
     */
    public function verb(): string
    {
        return match ($this) {
            self::Created => 'angelegt',
            self::Updated => 'bearbeitet',
            self::Deleted => 'gelöscht',
        };
    }
}
