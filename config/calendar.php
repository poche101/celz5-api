<?php

return [
    'max_images_per_event' => 5,
    'max_image_size' => 5120, // KB
    'allowed_image_types' => ['jpeg', 'png', 'jpg', 'gif', 'webp'],
    'default_timezone' => 'UTC',
    'default_reminders' => [30, 15], // minutes before
    'ical_prodid' => '-//YourChurchApp//Calendar//EN',
    'recurrence_limits' => [
        'max_occurrences' => 365,
        'max_years' => 5
    ],
    'meeting_platforms' => [
        'zoom' => 'Zoom Meeting',
        'google_meet' => 'Google Meet',
        'teams' => 'Microsoft Teams',
        'webex' => 'Cisco Webex',
        'other' => 'Other'
    ]
];