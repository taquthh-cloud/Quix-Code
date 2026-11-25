<?php

return [
    'errors' => [
        'invalid' => 'The file could not be read. Please choose it again.',
        'empty' => 'The file is empty.',
        'too_large' => 'The file is larger than :max MB.',
        'type' => 'This file type is not allowed. Use JPG, PNG, WebP, or PDF.',
        'extension' => 'The file extension does not match its contents.',
        'corrupt' => 'The file is damaged or is not a valid image/PDF.',
        'dimensions' => 'The image dimensions are too large. Resize it and try again.',
        'embedded_code' => 'The file contains hidden code and was rejected.',
        'active_content' => 'The PDF contains active content (scripts or attachments) and was rejected.',
        'malware' => 'The file was detected as harmful and rejected.',
        'scan_failed' => 'The file could not be security-checked yet. Please try again later.',
    ],
];
