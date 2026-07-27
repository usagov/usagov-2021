<?php

return [
  'public_uri' => [
    '/s3/files/featured%20images/example.png?itok=abc' => 'public://featured+images/example.png',
    'https://cms-dev.usa.gov/sites/default/files/example image.png#preview' => 'public://example+image.png',
    '/files/documents/benefits.pdf' => 'public://documents/benefits.pdf',
    'https://example.gov/assets/example.png' => NULL,
  ],
  'normalized_url' => [
    'https://www.usa.gov/s3/files/example.png?itok=abc' => 'https://www.usa.gov/files/example.png?itok=abc',
    '/sites/default/files/example.png#preview' => '/files/example.png#preview',
    '/files/example.png' => '/files/example.png',
  ],
  'static_output_relative_path' => [
    '/s3/files/featured%20images/example.png?itok=abc' => 'files/featured%20images/example.png',
    'https://cms-dev.usa.gov/sites/default/files/example image.png#preview' => 'files/example image.png',
    '/files/documents/benefits.pdf' => 'files/documents/benefits.pdf',
    'https://example.gov/assets/example.png' => NULL,
  ],
];
