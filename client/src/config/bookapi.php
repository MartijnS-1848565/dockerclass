<?php

return [
    // Inside the Docker network the API is reachable by its *service name*: http://api:8000
    // (not localhost: inside this container, localhost is the client container itself).
    'url' => env('BOOK_API_URL', 'http://api:8000/api'),
    'key' => env('BOOK_API_KEY'),

    // The same books, served in other API styles by the same api container.
    'graphql_url' => env('BOOK_GRAPHQL_URL', 'http://api:8000/graphql'),
    'soap_wsdl' => env('BOOK_SOAP_WSDL', 'http://api:8000/api/soap?wsdl'),
];
