<?php
// Static option lists used by the registration and trip forms.

const NATIONALITIES = [
    'Lebanon', 'Syria', 'Jordan', 'Palestine', 'Egypt', 'Iraq', 'Saudi Arabia', 'United Arab Emirates', 'Kuwait', 'Qatar',
    'Bahrain', 'Oman', 'Morocco', 'Tunisia', 'Algeria', 'Turkey', 'Cyprus', 'Greece', 'Armenia', 'France', 'Germany',
    'Italy', 'Spain', 'Portugal', 'Belgium', 'Netherlands', 'Switzerland', 'United Kingdom', 'Ireland', 'Sweden', 'Norway',
    'Denmark', 'Russia', 'Ukraine', 'Poland', 'United States', 'Canada', 'Mexico', 'Brazil', 'Argentina', 'Venezuela',
    'Colombia', 'Australia', 'New Zealand', 'China', 'Japan', 'South Korea', 'India', 'Pakistan', 'Philippines',
    'Nigeria', 'Senegal', 'Ivory Coast', 'South Africa', 'Other',
];

const LANGUAGES = ['English', 'Arabic', 'French', 'Spanish', 'German', 'Italian', 'Portuguese', 'Turkish', 'Armenian', 'Russian', 'Chinese', 'Other'];

function options(array $items, ?string $selected): string {
    $html = '';
    foreach ($items as $i) {
        $html .= '<option value="' . e($i) . '"' . ($i === $selected ? ' selected' : '') . '>' . e($i) . '</option>';
    }
    return $html;
}
