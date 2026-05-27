<?php

declare(strict_types=1);

return [

    'errors' => [
        'rate_limited' => 'RDW rate limit reached. Try again in :secondss.',
        'rejected' => 'The generated query was rejected. Try rephrasing your question.',
        'timeout' => 'RDW took too long to answer this query. Please try again in a moment.',
        'malformed' => 'The generated query was malformed. Try rephrasing your question.',
        'unexpected' => 'Something went wrong building or running the query.',
        'not_found' => 'That query result was not found.',
        'feedback_forbidden' => 'This result has already been rated by someone else.',
    ],

    'unsupported' => 'This question could not be answered with the available data.',

];
