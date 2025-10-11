<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;

class MockObject implements Authenticatable
{

    private $validated = false;

    // Metodi Authenticatable
    public function getAuthIdentifierName()
    {
        return 'id';
    }

    public function getAuthIdentifier()
    {
        return $this->id ?? 1;
    }

    public function getAuthPassword()
    {
        return '';
    }

    public function getAuthPasswordName()
    {
        return 'password';
    }

    public function getRememberToken()
    {
        return null;
    }

    public function setRememberToken($value)
    {
        // Non fare nulla
    }

    public function getRememberTokenName()
    {
        return null;
    }

    // ⚠️ Valida tutte le proprietà esistenti
    private function validateProperties()
    {
        if ($this->validated) {
            return;
        }

        foreach (get_object_vars($this) as $prop => $value) {
            if ($prop === 'validated') continue;

            // Se è int e non è 'id', converti in oggetto
            if (is_int($value) && $prop !== 'id') {
                $obj = new MockObject();
                $obj->id = $value;
                $obj->name = ucfirst($prop) . ' Mock';
                $this->$prop = $obj;
            }
        }

        $this->validated = true;
    }

    // Metodi MockObject
    public function __get($name)
    {
        // Valida prima di accedere
        $this->validateProperties();

        // Se esiste, ritornalo
        if (property_exists($this, $name)) {
            $value = $this->$name;

            // ⚠️ CRITICO: Se è null, genera
            if ($value === null) {
                $this->$name = $this->generatePropertyValue($name);
                return $this->$name;
            }

            // Blocco int
            if (is_int($value) && $name !== 'id') {
                $obj = new MockObject();
                $obj->id = $value;
                $obj->name = ucfirst($name) . ' Mock';
                $this->$name = $obj;
                return $this->$name;
            }

            return $this->$name;
        }

        // Auto-genera (MAI null)
        $this->$name = $this->generatePropertyValue($name);
        return $this->$name;
    }
    public function __set($name, $value)
    {
        // ⚠️ CRITICO: BLOCCA MockObject per qualsiasi proprietà date
        if ($value instanceof MockObject) {
            // Se è una proprietà date, usa Carbon
            if (str_contains($name, 'date') || str_contains($name, '_at')) {
                $this->$name = now();
                return;
            }
            // Altrimenti lascia l'oggetto
        }

        // Blocca int per non-id
        if (is_int($value) && $name !== 'id') {
            $obj = new MockObject();
            $obj->id = $value;
            $obj->name = ucfirst($name) . ' Mock';
            $this->$name = $obj;
            return;
        }

        // Null
        if ($value === null) {
            $this->$name = $this->generatePropertyValue($name);
            return;
        }

        $this->$name = $value;
    }

private function generatePropertyValue($name) {
    // Date SEMPRE Carbon (MAI null, MAI MockObject)
    if (str_contains($name, 'date') || str_contains($name, '_at')) {
        return now();
    }

    // Relazioni SEMPRE oggetti (MAI null)
    $relations = [
        'user', 'zone', 'club', 'tournament', 'assignment', 'referee',
        'tournamentType', 'notification', 'assignedUser', 'selectedUser'
    ];

    if (in_array($name, $relations)) {
        $obj = new MockObject();
        $obj->id = rand(1, 999);
        $obj->name = ucfirst($name) . ' Mock';

        // User-like
        if (in_array($name, ['user', 'assignedUser', 'referee'])) {
            $obj->email = 'user@example.com';
            $obj->user_type = 'referee';
            $obj->level = 'nazionale';
        }

        return $obj;
    }

    // Valori semplici
    if ($name === 'id') return rand(1, 999);
    if ($name === 'name') return 'Mock Name';
    // ... altri valori semplici

    // ⚠️ DEFAULT FINALE: SEMPRE oggetto, MAI null
    $obj = new MockObject();
    $obj->id = rand(1, 999);
    $obj->name = ucfirst($name) . ' Mock';
    return $obj;
}

    public function __isset($name)
    {
        return true;
    }

    public function __toString()
    {
        if (isset($this->name) && is_string($this->name)) {
            return $this->name;
        }
        return 'Mock Object';
    }

    public function __call($method, $args)
    {
        // Metodi che ritornano QueryBuilder
        if (in_array($method, ['assignments', 'users', 'referees', 'where', 'orderBy'])) {
            // Ritorna un QueryBuilder mock
            return new class extends MockObject {
                public function with(...$relations)
                {
                    return $this;
                }

                public function load(...$relations)
                {
                    return $this;
                }

                public function where(...$args)
                {
                    return $this;
                }

                public function orderBy(...$args)
                {
                    return $this;
                }

                public function get()
                {
                    $item = new MockObject();
                    $item->id = 1;
                    $item->role = 'Direttore di Torneo';
                    $item->tournament_id = 1;
                    $item->user_id = 1;

                    $user = new MockObject();
                    $user->id = 1;
                    $user->name = 'Mock User';
                    $user->email = 'user@example.com';
                    $user->user_type = 'referee';
                    $user->level = 'nazionale';

                    $referee = new MockObject();
                    $referee->id = 1;
                    $referee->referee_code = 'REF001';
                    $referee->level_label = 'Nazionale';
                    $user->referee = $referee;

                    $item->user = $user;

                    // ⚠️ USA UniversalCollection
                    return new \App\Support\UniversalCollection([$item]);
                }

                public function count()
                {
                    return 1;
                }

                public function first()
                {
                    return $this->get()->first();
                }
            };
        }

        // ... resto del codice invariato
    }
}
