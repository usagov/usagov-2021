<?php

return [
  'public_uri' => [
    '/s3/files/featured%20images/example.png?itok=abc' => 'public://featured+images/example.png',
    'https://cms-dev.usa.gov/sites/default/files/example image.png#preview' => 'public://example+image.png',
    '/files/documents/benefits.pdf' => 'public://documents/benefits.pdf',
    'https://www.usa.gov/s3/files/2025-08/report.pdf' => 'public://2025-08/report.pdf',
    'https://example.gov/assets/example.png' => NULL,
    // Off-site agency links also use /sites/default/files/ and /files/ paths;
    // they must not be mapped onto our public:// scheme.
    'https://eclkc.ohs.acf.hhs.gov/sites/default/files/pdf/no-search/doc.pdf' => NULL,
    'https://home.army.mil/natick/application/files/4115/dd1172-2.pdf' => NULL,
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
    'https://eclkc.ohs.acf.hhs.gov/sites/default/files/pdf/no-search/doc.pdf' => NULL,
  ],
  'generated_static_asset' => [
    '/files/js/js_example.js' => TRUE,
    '/files/css/css_example.css' => TRUE,
    '/s3/files/styles/large/public/example.png' => FALSE,
  ],
  // Raw candidate URLs extracted from an HTML document, before any public://
  // filtering. Non-file URLs (page links, off-site assets) are intentionally
  // included here because callers filter them out downstream.
  'referenced_urls' => [
    '<img src="/s3/files/a.png">'
    . '<img srcset="/files/b-320.png 320w, /files/c-640.png 640w">'
    . '<a href="/files/documents/benefits.pdf">doc</a>'
    . '<a href="/about-us">nav</a>'
    . '<div style="background:url(\'/files/bg.png\')"></div>'
    . '<style>@font-face{src:url(/files/fonts/font.woff2)}</style>'
    . '<script src="https://example.gov/app.js"></script>' => [
      '/about-us',
      '/files/b-320.png',
      '/files/bg.png',
      '/files/c-640.png',
      '/files/documents/benefits.pdf',
      '/files/fonts/font.woff2',
      '/s3/files/a.png',
      'https://example.gov/app.js',
    ],
  ],
];
