<?php

return [
  'public_uri' => [
    '/s3/files/featured%20images/example.png?itok=abc' => 'public://s3/files/featured+images/example.png',
    'https://cms-dev.usa.gov/sites/default/files/example image.png#preview' => 'public://sites/default/files/example+image.png',
    '/files/documents/benefits.pdf' => 'public://files/documents/benefits.pdf',
    'https://example.gov/assets/example.png' => NULL,
  ],
  'normalized_url' => [
    'https://www.usa.gov/s3/files/example.png?itok=abc' => 'https://www.usa.gov/files/example.png?itok=abc',
    '/sites/default/files/example.png#preview' => '/files/example.png#preview',
    '/files/example.png' => '/files/example.png',
  ],
];
