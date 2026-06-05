<?php

declare(strict_types=1);

return [

    'errors' => [
        'rate_limited' => 'RDW-snelheidslimiet bereikt. Probeer het opnieuw over :secondss.',
        'rejected' => 'De gegenereerde query werd afgewezen. Probeer je vraag anders te formuleren.',
        'timeout' => 'RDW deed er te lang over om deze query te beantwoorden. Probeer het zo opnieuw.',
        'malformed' => 'De gegenereerde query was onjuist opgebouwd. Probeer je vraag anders te formuleren.',
        'unexpected' => 'Er ging iets mis bij het opbouwen of uitvoeren van de query.',
        'not_found' => 'Dat queryresultaat is niet gevonden.',
        'feedback_forbidden' => 'Dit resultaat is al beoordeeld door iemand anders.',
    ],

    'unsupported' => 'Deze vraag kon niet worden beantwoord met de beschikbare gegevens.',

    'refusal' => [
        'too_broad' => 'Dit komt overeen met te veel voertuigen om tussen datasets te combineren. Maak het specifieker — bijvoorbeeld met een bepaald model, bouwjaar of een minder vaak voorkomend merk.',
        'no_matches' => 'Er is geen voertuig gevonden dat aan de vraag voldoet — controleer het kenteken of de merknaam.',
    ],

];
