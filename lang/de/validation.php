<?php

/*
 * Deutsche Fehlermeldungen für die in der App verwendeten Regeln.
 * Für weitere Regeln: https://github.com/Laravel-Lang/lang oder hier ergänzen.
 */
return [
    'alpha_dash' => ':Attribute darf nur Buchstaben, Zahlen, Binde- und Unterstriche enthalten.',
    'array' => ':Attribute muss eine Liste sein.',
    'between' => [
        'numeric' => ':Attribute muss zwischen :min und :max liegen.',
        'string' => ':Attribute muss zwischen :min und :max Zeichen lang sein.',
        'array' => ':Attribute muss zwischen :min und :max Einträge haben.',
        'file' => ':Attribute muss zwischen :min und :max Kilobytes groß sein.',
    ],
    'boolean' => ':Attribute muss wahr oder falsch sein.',
    'confirmed' => ':Attribute stimmt nicht mit der Wiederholung überein.',
    'current_password' => 'Das Passwort ist falsch.',
    'enum' => 'Der gewählte Wert für :attribute ist ungültig.',
    'hex_color' => ':Attribute muss eine gültige Hex-Farbe sein.',
    'image' => ':Attribute muss ein Bild sein.',
    'integer' => ':Attribute muss eine ganze Zahl sein.',
    'max' => [
        'numeric' => ':Attribute darf maximal :max sein.',
        'string' => ':Attribute darf maximal :max Zeichen lang sein.',
        'array' => ':Attribute darf maximal :max Einträge haben.',
        'file' => ':Attribute darf maximal :max Kilobytes groß sein.',
    ],
    'min' => [
        'numeric' => ':Attribute muss mindestens :min sein.',
        'string' => ':Attribute muss mindestens :min Zeichen lang sein.',
        'array' => ':Attribute muss mindestens :min Eintrag haben.',
        'file' => ':Attribute muss mindestens :min Kilobytes groß sein.',
    ],
    'required' => ':Attribute ist ein Pflichtfeld.',
    'string' => ':Attribute muss ein Text sein.',
    'unique' => ':Attribute ist bereits vergeben.',
    'uploaded' => ':Attribute konnte nicht hochgeladen werden.',

    'custom' => [],
    'attributes' => [],
];
