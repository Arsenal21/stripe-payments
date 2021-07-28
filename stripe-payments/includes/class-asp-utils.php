<?php

class ASP_Utils {

	protected static $textdomain_paths = array(
		WP_ASP_PLUGIN_PATH . '/languages/',
		WP_CONTENT_DIR . '/languages/plugins/',
		WP_CONTENT_DIR . '/languages/loco/plugins/',
	);

	protected static $lang_code_locale = array(
		'fr' => 'fr_FR',
		'de' => 'de_DE',
		'da' => 'da_DK',
		'nl' => 'nl_NL',
		'en' => 'en_US',
		'he' => 'he_IL',
		'it' => 'it_IT',
		'lt' => 'lt_LT',
		'ms' => 'ms_MY',
		'nb' => 'nb_NO',
		'pl' => 'pl_PL',
		'pt' => 'pt_PT',
		'ru' => 'ru_RU',
		'zh' => 'zh_CN',
		'es' => 'es_ES',
		'sv' => 'sv_SE',
	);

	protected static $textdomain_backup;

	public static function get_countries_untranslated() {
		$countries = array(
			''   => '',
			'AF' => 'Afghanistan',
			'AX' => 'Aland Islands',
			'AL' => 'Albania',
			'DZ' => 'Algeria',
			'AS' => 'American Samoa',
			'AD' => 'Andorra',
			'AO' => 'Angola',
			'AI' => 'Anguilla',
			'AQ' => 'Antarctica',
			'AG' => 'Antigua and Barbuda',
			'AR' => 'Argentina',
			'AM' => 'Armenia',
			'AW' => 'Aruba',
			'AU' => 'Australia',
			'AT' => 'Austria',
			'AZ' => 'Azerbaijan',
			'BS' => 'Bahamas',
			'BH' => 'Bahrain',
			'BD' => 'Bangladesh',
			'BB' => 'Barbados',
			'BY' => 'Belarus',
			'BE' => 'Belgium',
			'BZ' => 'Belize',
			'BJ' => 'Benin',
			'BM' => 'Bermuda',
			'BT' => 'Bhutan',
			'BO' => 'Bolivia',
			'BQ' => 'Bonaire',
			'BA' => 'Bosnia and Herzegovina',
			'BW' => 'Botswana',
			'BV' => 'Bouvet Island',
			'BR' => 'Brazil',
			'IO' => 'British Indian Ocean Territory',
			'VG' => 'British Virgin Islands',
			'BN' => 'Brunei',
			'BG' => 'Bulgaria',
			'BF' => 'Burkina Faso',
			'BI' => 'Burundi',
			'KH' => 'Cambodia',
			'CM' => 'Cameroon',
			'CA' => 'Canada',
			'CV' => 'Cape Verde',
			'KY' => 'Cayman Islands',
			'CF' => 'Central African Republic',
			'TD' => 'Chad',
			'CL' => 'Chile',
			'CN' => 'China',
			'CX' => 'Christmas Island',
			'CC' => 'Cocos (Keeling) Islands',
			'CO' => 'Colombia',
			'KM' => 'Comoros',
			'CD' => 'Congo, Democratic Republic of',
			'CG' => 'Congo, Republic of',
			'CK' => 'Cook Islands',
			'CR' => 'Costa Rica',
			'HR' => 'Croatia',
			'CU' => 'Cuba',
			'CW' => 'Curacao',
			'CY' => 'Cyprus',
			'CZ' => 'Czech Republic',
			'DK' => 'Denmark',
			'DJ' => 'Djibouti',
			'DM' => 'Dominica',
			'DO' => 'Dominican Republic',
			'EC' => 'Ecuador',
			'EG' => 'Egypt',
			'SV' => 'El Salvador',
			'GQ' => 'Equatorial Guinea',
			'ER' => 'Eritrea',
			'EE' => 'Estonia',
			'ET' => 'Ethiopia',
			'FK' => 'Falkland Islands',
			'FO' => 'Faroe Islands',
			'FJ' => 'Fiji',
			'FI' => 'Finland',
			'FR' => 'France',
			'GF' => 'French Guiana',
			'PF' => 'French Polynesia',
			'TF' => 'French Southern and Antarctic Lands',
			'GA' => 'Gabon',
			'GM' => 'Gambia',
			'GE' => 'Georgia',
			'DE' => 'Germany',
			'GH' => 'Ghana',
			'GI' => 'Gibraltar',
			'GR' => 'Greece',
			'GL' => 'Greenland',
			'GD' => 'Grenada',
			'GP' => 'Guadeloupe',
			'GU' => 'Guam',
			'GT' => 'Guatemala',
			'GG' => 'Guernsey',
			'GN' => 'Guinea',
			'GW' => 'Guinea-Bissau',
			'GY' => 'Guyana',
			'HT' => 'Haiti',
			'HM' => 'Heard Island and McDonald Islands',
			'HN' => 'Honduras',
			'HK' => 'Hong Kong',
			'HU' => 'Hungary',
			'IS' => 'Iceland',
			'IN' => 'India',
			'ID' => 'Indonesia',
			'IR' => 'Iran',
			'IQ' => 'Iraq',
			'IE' => 'Ireland',
			'IM' => 'Isle of Man',
			'IL' => 'Israel',
			'IT' => 'Italy',
			'CI' => 'Ivory Coast',
			'JM' => 'Jamaica',
			'JP' => 'Japan',
			'JE' => 'Jersey',
			'JO' => 'Jordan',
			'KZ' => 'Kazakhstan',
			'KE' => 'Kenya',
			'KI' => 'Kiribati',
			'KP' => "Korea, Democratic People's Republic of",
			'KR' => 'Korea, Republic of',
			'XK' => 'Kosovo',
			'KW' => 'Kuwait',
			'KG' => 'Kyrgyzstan',
			'LA' => 'Laos',
			'LV' => 'Latvia',
			'LB' => 'Lebanon',
			'LS' => 'Lesotho',
			'LR' => 'Liberia',
			'LY' => 'Libya',
			'LI' => 'Liechtenstein',
			'LT' => 'Lithuania',
			'LU' => 'Luxembourg',
			'MO' => 'Macau',
			'MK' => 'Macedonia',
			'MG' => 'Madagascar',
			'MW' => 'Malawi',
			'MY' => 'Malaysia',
			'MV' => 'Maldives',
			'ML' => 'Mali',
			'MT' => 'Malta',
			'MH' => 'Marshall Islands',
			'MQ' => 'Martinique',
			'MR' => 'Mauritania',
			'MU' => 'Mauritius',
			'YT' => 'Mayotte',
			'MX' => 'Mexico',
			'FM' => 'Micronesia',
			'MD' => 'Moldova',
			'MC' => 'Monaco',
			'MN' => 'Mongolia',
			'ME' => 'Montenegro',
			'MS' => 'Montserrat',
			'MA' => 'Morocco',
			'MZ' => 'Mozambique',
			'MM' => 'Myanmar',
			'NA' => 'Namibia',
			'NR' => 'Nauru',
			'NP' => 'Nepal',
			'NL' => 'Netherlands',
			'NC' => 'New Caledonia',
			'NZ' => 'New Zealand',
			'NI' => 'Nicaragua',
			'NE' => 'Niger',
			'NG' => 'Nigeria',
			'NU' => 'Niue',
			'NF' => 'Norfolk Island',
			'MP' => 'Northern Mariana Islands',
			'NO' => 'Norway',
			'OM' => 'Oman',
			'PK' => 'Pakistan',
			'PW' => 'Palau',
			'PS' => 'Palestine',
			'PA' => 'Panama',
			'PG' => 'Papua New Guinea',
			'PY' => 'Paraguay',
			'PE' => 'Peru',
			'PH' => 'Philippines',
			'PN' => 'Pitcairn Islands',
			'PL' => 'Poland',
			'PT' => 'Portugal',
			'PR' => 'Puerto Rico',
			'QA' => 'Qatar',
			'RE' => 'Reunion',
			'RO' => 'Romania',
			'RU' => 'Russia',
			'RW' => 'Rwanda',
			'BL' => 'Saint Barthelemy',
			'SH' => 'Saint Helena, Ascension, and Tristan da Cunha',
			'KN' => 'Saint Kitts and Nevis',
			'LC' => 'Saint Lucia',
			'MF' => 'Saint Martin',
			'PM' => 'Saint Pierre and Miquelon',
			'VC' => 'Saint Vincent and the Grenadines',
			'WS' => 'Samoa',
			'SM' => 'San Marino',
			'ST' => 'Sao Tome and Principe',
			'SA' => 'Saudi Arabia',
			'SN' => 'Senegal',
			'RS' => 'Serbia',
			'SC' => 'Seychelles',
			'SL' => 'Sierra Leone',
			'SG' => 'Singapore',
			'SX' => 'Sint Maarten',
			'SK' => 'Slovakia',
			'SI' => 'Slovenia',
			'SB' => 'Solomon Islands',
			'SO' => 'Somalia',
			'ZA' => 'South Africa',
			'GS' => 'South Georgia',
			'SS' => 'South Sudan',
			'ES' => 'Spain',
			'LK' => 'Sri Lanka',
			'SD' => 'Sudan',
			'SR' => 'Suriname',
			'SJ' => 'Svalbard and Jan Mayen',
			'SZ' => 'Swaziland',
			'SE' => 'Sweden',
			'CH' => 'Switzerland',
			'SY' => 'Syria',
			'TW' => 'Taiwan',
			'TJ' => 'Tajikistan',
			'TZ' => 'Tanzania',
			'TH' => 'Thailand',
			'TL' => 'Timor-Leste',
			'TG' => 'Togo',
			'TK' => 'Tokelau',
			'TO' => 'Tonga',
			'TT' => 'Trinidad and Tobago',
			'TN' => 'Tunisia',
			'TR' => 'Turkey',
			'TM' => 'Turkmenistan',
			'TC' => 'Turks and Caicos Islands',
			'TV' => 'Tuvalu',
			'UG' => 'Uganda',
			'UA' => 'Ukraine',
			'AE' => 'United Arab Emirates',
			'GB' => 'United Kingdom',
			'US' => 'United States',
			'UM' => 'United States Minor Outlying Islands',
			'VI' => 'United States Virgin Islands',
			'UY' => 'Uruguay',
			'UZ' => 'Uzbekistan',
			'VU' => 'Vanuatu',
			'VA' => 'Vatican City',
			'VE' => 'Venezuela',
			'VN' => 'Vietnam',
			'WF' => 'Wallis and Futuna',
			'EH' => 'Western Sahara',
			'YE' => 'Yemen',
			'ZM' => 'Zambia',
			'ZW' => 'Zimbabwe',
		);
		return $countries;
	}

	public static function get_countries() {
		$countries = array(
			''   => '—',
			'AF' => __( 'Afghanistan', 'stripe-payments' ),
			'AX' => __( 'Aland Islands', 'stripe-payments' ),
			'AL' => __( 'Albania', 'stripe-payments' ),
			'DZ' => __( 'Algeria', 'stripe-payments' ),
			'AS' => __( 'American Samoa', 'stripe-payments' ),
			'AD' => __( 'Andorra', 'stripe-payments' ),
			'AO' => __( 'Angola', 'stripe-payments' ),
			'AI' => __( 'Anguilla', 'stripe-payments' ),
			'AQ' => __( 'Antarctica', 'stripe-payments' ),
			'AG' => __( 'Antigua and Barbuda', 'stripe-payments' ),
			'AR' => __( 'Argentina', 'stripe-payments' ),
			'AM' => __( 'Armenia', 'stripe-payments' ),
			'AW' => __( 'Aruba', 'stripe-payments' ),
			'AU' => __( 'Australia', 'stripe-payments' ),
			'AT' => __( 'Austria', 'stripe-payments' ),
			'AZ' => __( 'Azerbaijan', 'stripe-payments' ),
			'BS' => __( 'Bahamas', 'stripe-payments' ),
			'BH' => __( 'Bahrain', 'stripe-payments' ),
			'BD' => __( 'Bangladesh', 'stripe-payments' ),
			'BB' => __( 'Barbados', 'stripe-payments' ),
			'BY' => __( 'Belarus', 'stripe-payments' ),
			'BE' => __( 'Belgium', 'stripe-payments' ),
			'BZ' => __( 'Belize', 'stripe-payments' ),
			'BJ' => __( 'Benin', 'stripe-payments' ),
			'BM' => __( 'Bermuda', 'stripe-payments' ),
			'BT' => __( 'Bhutan', 'stripe-payments' ),
			'BO' => __( 'Bolivia', 'stripe-payments' ),
			'BQ' => __( 'Bonaire', 'stripe-payments' ),
			'BA' => __( 'Bosnia and Herzegovina', 'stripe-payments' ),
			'BW' => __( 'Botswana', 'stripe-payments' ),
			'BV' => __( 'Bouvet Island', 'stripe-payments' ),
			'BR' => __( 'Brazil', 'stripe-payments' ),
			'IO' => __( 'British Indian Ocean Territory', 'stripe-payments' ),
			'VG' => __( 'British Virgin Islands', 'stripe-payments' ),
			'BN' => __( 'Brunei', 'stripe-payments' ),
			'BG' => __( 'Bulgaria', 'stripe-payments' ),
			'BF' => __( 'Burkina Faso', 'stripe-payments' ),
			'BI' => __( 'Burundi', 'stripe-payments' ),
			'KH' => __( 'Cambodia', 'stripe-payments' ),
			'CM' => __( 'Cameroon', 'stripe-payments' ),
			'CA' => __( 'Canada', 'stripe-payments' ),
			'CV' => __( 'Cape Verde', 'stripe-payments' ),
			'KY' => __( 'Cayman Islands', 'stripe-payments' ),
			'CF' => __( 'Central African Republic', 'stripe-payments' ),
			'TD' => __( 'Chad', 'stripe-payments' ),
			'CL' => __( 'Chile', 'stripe-payments' ),
			'CN' => __( 'China', 'stripe-payments' ),
			'CX' => __( 'Christmas Island', 'stripe-payments' ),
			'CC' => __( 'Cocos (Keeling) Islands', 'stripe-payments' ),
			'CO' => __( 'Colombia', 'stripe-payments' ),
			'KM' => __( 'Comoros', 'stripe-payments' ),
			'CD' => __( 'Congo, Democratic Republic of', 'stripe-payments' ),
			'CG' => __( 'Congo, Republic of', 'stripe-payments' ),
			'CK' => __( 'Cook Islands', 'stripe-payments' ),
			'CR' => __( 'Costa Rica', 'stripe-payments' ),
			'HR' => __( 'Croatia', 'stripe-payments' ),
			'CU' => __( 'Cuba', 'stripe-payments' ),
			'CW' => __( 'Curacao', 'stripe-payments' ),
			'CY' => __( 'Cyprus', 'stripe-payments' ),
			'CZ' => __( 'Czech Republic', 'stripe-payments' ),
			'DK' => __( 'Denmark', 'stripe-payments' ),
			'DJ' => __( 'Djibouti', 'stripe-payments' ),
			'DM' => __( 'Dominica', 'stripe-payments' ),
			'DO' => __( 'Dominican Republic', 'stripe-payments' ),
			'EC' => __( 'Ecuador', 'stripe-payments' ),
			'EG' => __( 'Egypt', 'stripe-payments' ),
			'SV' => __( 'El Salvador', 'stripe-payments' ),
			'GQ' => __( 'Equatorial Guinea', 'stripe-payments' ),
			'ER' => __( 'Eritrea', 'stripe-payments' ),
			'EE' => __( 'Estonia', 'stripe-payments' ),
			'ET' => __( 'Ethiopia', 'stripe-payments' ),
			'FK' => __( 'Falkland Islands', 'stripe-payments' ),
			'FO' => __( 'Faroe Islands', 'stripe-payments' ),
			'FJ' => __( 'Fiji', 'stripe-payments' ),
			'FI' => __( 'Finland', 'stripe-payments' ),
			'FR' => __( 'France', 'stripe-payments' ),
			'GF' => __( 'French Guiana', 'stripe-payments' ),
			'PF' => __( 'French Polynesia', 'stripe-payments' ),
			'TF' => __( 'French Southern and Antarctic Lands', 'stripe-payments' ),
			'GA' => __( 'Gabon', 'stripe-payments' ),
			'GM' => __( 'Gambia', 'stripe-payments' ),
			'GE' => __( 'Georgia', 'stripe-payments' ),
			'DE' => __( 'Germany', 'stripe-payments' ),
			'GH' => __( 'Ghana', 'stripe-payments' ),
			'GI' => __( 'Gibraltar', 'stripe-payments' ),
			'GR' => __( 'Greece', 'stripe-payments' ),
			'GL' => __( 'Greenland', 'stripe-payments' ),
			'GD' => __( 'Grenada', 'stripe-payments' ),
			'GP' => __( 'Guadeloupe', 'stripe-payments' ),
			'GU' => __( 'Guam', 'stripe-payments' ),
			'GT' => __( 'Guatemala', 'stripe-payments' ),
			'GG' => __( 'Guernsey', 'stripe-payments' ),
			'GN' => __( 'Guinea', 'stripe-payments' ),
			'GW' => __( 'Guinea-Bissau', 'stripe-payments' ),
			'GY' => __( 'Guyana', 'stripe-payments' ),
			'HT' => __( 'Haiti', 'stripe-payments' ),
			'HM' => __( 'Heard Island and McDonald Islands', 'stripe-payments' ),
			'HN' => __( 'Honduras', 'stripe-payments' ),
			'HK' => __( 'Hong Kong', 'stripe-payments' ),
			'HU' => __( 'Hungary', 'stripe-payments' ),
			'IS' => __( 'Iceland', 'stripe-payments' ),
			'IN' => __( 'India', 'stripe-payments' ),
			'ID' => __( 'Indonesia', 'stripe-payments' ),
			'IR' => __( 'Iran', 'stripe-payments' ),
			'IQ' => __( 'Iraq', 'stripe-payments' ),
			'IE' => __( 'Ireland', 'stripe-payments' ),
			'IM' => __( 'Isle of Man', 'stripe-payments' ),
			'IL' => __( 'Israel', 'stripe-payments' ),
			'IT' => __( 'Italy', 'stripe-payments' ),
			'CI' => __( 'Ivory Coast', 'stripe-payments' ),
			'JM' => __( 'Jamaica', 'stripe-payments' ),
			'JP' => __( 'Japan', 'stripe-payments' ),
			'JE' => __( 'Jersey', 'stripe-payments' ),
			'JO' => __( 'Jordan', 'stripe-payments' ),
			'KZ' => __( 'Kazakhstan', 'stripe-payments' ),
			'KE' => __( 'Kenya', 'stripe-payments' ),
			'KI' => __( 'Kiribati', 'stripe-payments' ),
			'KP' => __( "Korea, Democratic People's Republic of", 'stripe-payments' ),
			'KR' => __( 'Korea, Republic of', 'stripe-payments' ),
			'XK' => __( 'Kosovo', 'stripe-payments' ),
			'KW' => __( 'Kuwait', 'stripe-payments' ),
			'KG' => __( 'Kyrgyzstan', 'stripe-payments' ),
			'LA' => __( 'Laos', 'stripe-payments' ),
			'LV' => __( 'Latvia', 'stripe-payments' ),
			'LB' => __( 'Lebanon', 'stripe-payments' ),
			'LS' => __( 'Lesotho', 'stripe-payments' ),
			'LR' => __( 'Liberia', 'stripe-payments' ),
			'LY' => __( 'Libya', 'stripe-payments' ),
			'LI' => __( 'Liechtenstein', 'stripe-payments' ),
			'LT' => __( 'Lithuania', 'stripe-payments' ),
			'LU' => __( 'Luxembourg', 'stripe-payments' ),
			'MO' => __( 'Macau', 'stripe-payments' ),
			'MK' => __( 'Macedonia', 'stripe-payments' ),
			'MG' => __( 'Madagascar', 'stripe-payments' ),
			'MW' => __( 'Malawi', 'stripe-payments' ),
			'MY' => __( 'Malaysia', 'stripe-payments' ),
			'MV' => __( 'Maldives', 'stripe-payments' ),
			'ML' => __( 'Mali', 'stripe-payments' ),
			'MT' => __( 'Malta', 'stripe-payments' ),
			'MH' => __( 'Marshall Islands', 'stripe-payments' ),
			'MQ' => __( 'Martinique', 'stripe-payments' ),
			'MR' => __( 'Mauritania', 'stripe-payments' ),
			'MU' => __( 'Mauritius', 'stripe-payments' ),
			'YT' => __( 'Mayotte', 'stripe-payments' ),
			'MX' => __( 'Mexico', 'stripe-payments' ),
			'FM' => __( 'Micronesia', 'stripe-payments' ),
			'MD' => __( 'Moldova', 'stripe-payments' ),
			'MC' => __( 'Monaco', 'stripe-payments' ),
			'MN' => __( 'Mongolia', 'stripe-payments' ),
			'ME' => __( 'Montenegro', 'stripe-payments' ),
			'MS' => __( 'Montserrat', 'stripe-payments' ),
			'MA' => __( 'Morocco', 'stripe-payments' ),
			'MZ' => __( 'Mozambique', 'stripe-payments' ),
			'MM' => __( 'Myanmar', 'stripe-payments' ),
			'NA' => __( 'Namibia', 'stripe-payments' ),
			'NR' => __( 'Nauru', 'stripe-payments' ),
			'NP' => __( 'Nepal', 'stripe-payments' ),
			'NL' => __( 'Netherlands', 'stripe-payments' ),
			'NC' => __( 'New Caledonia', 'stripe-payments' ),
			'NZ' => __( 'New Zealand', 'stripe-payments' ),
			'NI' => __( 'Nicaragua', 'stripe-payments' ),
			'NE' => __( 'Niger', 'stripe-payments' ),
			'NG' => __( 'Nigeria', 'stripe-payments' ),
			'NU' => __( 'Niue', 'stripe-payments' ),
			'NF' => __( 'Norfolk Island', 'stripe-payments' ),
			'MP' => __( 'Northern Mariana Islands', 'stripe-payments' ),
			'NO' => __( 'Norway', 'stripe-payments' ),
			'OM' => __( 'Oman', 'stripe-payments' ),
			'PK' => __( 'Pakistan', 'stripe-payments' ),
			'PW' => __( 'Palau', 'stripe-payments' ),
			'PS' => __( 'Palestine', 'stripe-payments' ),
			'PA' => __( 'Panama', 'stripe-payments' ),
			'PG' => __( 'Papua New Guinea', 'stripe-payments' ),
			'PY' => __( 'Paraguay', 'stripe-payments' ),
			'PE' => __( 'Peru', 'stripe-payments' ),
			'PH' => __( 'Philippines', 'stripe-payments' ),
			'PN' => __( 'Pitcairn Islands', 'stripe-payments' ),
			'PL' => __( 'Poland', 'stripe-payments' ),
			'PT' => __( 'Portugal', 'stripe-payments' ),
			'PR' => __( 'Puerto Rico', 'stripe-payments' ),
			'QA' => __( 'Qatar', 'stripe-payments' ),
			'RE' => __( 'Reunion', 'stripe-payments' ),
			'RO' => __( 'Romania', 'stripe-payments' ),
			'RU' => __( 'Russia', 'stripe-payments' ),
			'RW' => __( 'Rwanda', 'stripe-payments' ),
			'BL' => __( 'Saint Barthelemy', 'stripe-payments' ),
			'SH' => __( 'Saint Helena, Ascension, and Tristan da Cunha', 'stripe-payments' ),
			'KN' => __( 'Saint Kitts and Nevis', 'stripe-payments' ),
			'LC' => __( 'Saint Lucia', 'stripe-payments' ),
			'MF' => __( 'Saint Martin', 'stripe-payments' ),
			'PM' => __( 'Saint Pierre and Miquelon', 'stripe-payments' ),
			'VC' => __( 'Saint Vincent and the Grenadines', 'stripe-payments' ),
			'WS' => __( 'Samoa', 'stripe-payments' ),
			'SM' => __( 'San Marino', 'stripe-payments' ),
			'ST' => __( 'Sao Tome and Principe', 'stripe-payments' ),
			'SA' => __( 'Saudi Arabia', 'stripe-payments' ),
			'SN' => __( 'Senegal', 'stripe-payments' ),
			'RS' => __( 'Serbia', 'stripe-payments' ),
			'SC' => __( 'Seychelles', 'stripe-payments' ),
			'SL' => __( 'Sierra Leone', 'stripe-payments' ),
			'SG' => __( 'Singapore', 'stripe-payments' ),
			'SX' => __( 'Sint Maarten', 'stripe-payments' ),
			'SK' => __( 'Slovakia', 'stripe-payments' ),
			'SI' => __( 'Slovenia', 'stripe-payments' ),
			'SB' => __( 'Solomon Islands', 'stripe-payments' ),
			'SO' => __( 'Somalia', 'stripe-payments' ),
			'ZA' => __( 'South Africa', 'stripe-payments' ),
			'GS' => __( 'South Georgia', 'stripe-payments' ),
			'SS' => __( 'South Sudan', 'stripe-payments' ),
			'ES' => __( 'Spain', 'stripe-payments' ),
			'LK' => __( 'Sri Lanka', 'stripe-payments' ),
			'SD' => __( 'Sudan', 'stripe-payments' ),
			'SR' => __( 'Suriname', 'stripe-payments' ),
			'SJ' => __( 'Svalbard and Jan Mayen', 'stripe-payments' ),
			'SZ' => __( 'Swaziland', 'stripe-payments' ),
			'SE' => __( 'Sweden', 'stripe-payments' ),
			'CH' => __( 'Switzerland', 'stripe-payments' ),
			'SY' => __( 'Syria', 'stripe-payments' ),
			'TW' => __( 'Taiwan', 'stripe-payments' ),
			'TJ' => __( 'Tajikistan', 'stripe-payments' ),
			'TZ' => __( 'Tanzania', 'stripe-payments' ),
			'TH' => __( 'Thailand', 'stripe-payments' ),
			'TL' => __( 'Timor-Leste', 'stripe-payments' ),
			'TG' => __( 'Togo', 'stripe-payments' ),
			'TK' => __( 'Tokelau', 'stripe-payments' ),
			'TO' => __( 'Tonga', 'stripe-payments' ),
			'TT' => __( 'Trinidad and Tobago', 'stripe-payments' ),
			'TN' => __( 'Tunisia', 'stripe-payments' ),
			'TR' => __( 'Turkey', 'stripe-payments' ),
			'TM' => __( 'Turkmenistan', 'stripe-payments' ),
			'TC' => __( 'Turks and Caicos Islands', 'stripe-payments' ),
			'TV' => __( 'Tuvalu', 'stripe-payments' ),
			'UG' => __( 'Uganda', 'stripe-payments' ),
			'UA' => __( 'Ukraine', 'stripe-payments' ),
			'AE' => __( 'United Arab Emirates', 'stripe-payments' ),
			'GB' => __( 'United Kingdom', 'stripe-payments' ),
			'US' => __( 'United States', 'stripe-payments' ),
			'UM' => __( 'United States Minor Outlying Islands', 'stripe-payments' ),
			'VI' => __( 'United States Virgin Islands', 'stripe-payments' ),
			'UY' => __( 'Uruguay', 'stripe-payments' ),
			'UZ' => __( 'Uzbekistan', 'stripe-payments' ),
			'VU' => __( 'Vanuatu', 'stripe-payments' ),
			'VA' => __( 'Vatican City', 'stripe-payments' ),
			'VE' => __( 'Venezuela', 'stripe-payments' ),
			'VN' => __( 'Vietnam', 'stripe-payments' ),
			'WF' => __( 'Wallis and Futuna', 'stripe-payments' ),
			'EH' => __( 'Western Sahara', 'stripe-payments' ),
			'YE' => __( 'Yemen', 'stripe-payments' ),
			'ZM' => __( 'Zambia', 'stripe-payments' ),
			'ZW' => __( 'Zimbabwe', 'stripe-payments' ),
		);
		return $countries;
	}

	public static function get_countries_opts( $selected = false ) {
		$countries = self::get_countries();
		asort( $countries );
		if ( isset( $countries[''] ) ) {
			array_unshift( $countries, array_pop( $countries ) );
		}

		$countries = apply_filters( 'asp_ng_pp_countries_list', $countries );
		$out       = '';
		$tpl       = '<option value="%s"%s>%s</option>';
		foreach ( $countries as $c_code => $c_name ) {
			$selected_str = '';
			if ( false !== $selected ) {
				if ( $c_code === $selected ) {
					$selected_str = ' selected';
				}
			}
			$out .= sprintf( $tpl, esc_attr( $c_code ), $selected_str, esc_html( $c_name ) );
		}
		return $out;
	}

	public static function get_currencies() {
		$currencies = array(
			''    => array( __( '(Default)', 'stripe-payments' ), '' ),
			'USD' => array( __( 'US Dollars (USD)', 'stripe-payments' ), '$' ),
			'EUR' => array( __( 'Euros (EUR)', 'stripe-payments' ), '€' ),
			'GBP' => array( __( 'Pounds Sterling (GBP)', 'stripe-payments' ), '£' ),
			'AUD' => array( __( 'Australian Dollars (AUD)', 'stripe-payments' ), 'AU$' ),
			'ARS' => array( __( 'Argentine Peso (ARS)', 'stripe-payments' ), 'ARS' ),
			'BAM' => array( __( 'Bosnia and Herzegovina Convertible Mark (BAM)', 'stripe-payments' ), 'KM' ),
			'BGN' => array( __( 'Bulgarian Lev (BGN)', 'stripe-payments' ), 'Лв.' ),
			'BRL' => array( __( 'Brazilian Real (BRL)', 'stripe-payments' ), 'R$' ),
			'CAD' => array( __( 'Canadian Dollars (CAD)', 'stripe-payments' ), 'CA$' ),
			'CLP' => array( __( 'Chilean Peso (CLP)', 'stripe-payments' ), 'CLP' ),
			'CNY' => array( __( 'Chinese Yuan (CNY)', 'stripe-payments' ), 'CN￥' ),
			'COP' => array( __( 'Colombian Peso (COP)', 'stripe-payments' ), 'COL$' ),
			'CZK' => array( __( 'Czech Koruna (CZK)', 'stripe-payments' ), 'Kč' ),
			'DKK' => array( __( 'Danish Krone (DKK)', 'stripe-payments' ), 'kr' ),
			'DOP' => array( __( 'Dominican Peso (DOP)', 'stripe-payments' ), 'RD$' ),
			'EGP' => array( __( 'Egyptian Pound (EGP)', 'stripe-payments' ), 'E£' ),
			'HKD' => array( __( 'Hong Kong Dollar (HKD)', 'stripe-payments' ), 'HK$' ),
			'HUF' => array( __( 'Hungarian Forint (HUF)', 'stripe-payments' ), 'Ft' ),
			'INR' => array( __( 'Indian Rupee (INR)', 'stripe-payments' ), '₹' ),
			'IDR' => array( __( 'Indonesia Rupiah (IDR)', 'stripe-payments' ), 'Rp' ),
			'ILS' => array( __( 'Israeli Shekel (ILS)', 'stripe-payments' ), '₪' ),
			'JPY' => array( __( 'Japanese Yen (JPY)', 'stripe-payments' ), '¥' ),
			'LBP' => array( __( 'Lebanese Pound (LBP)', 'stripe-payments' ), 'ل.ل' ),
			'MYR' => array( __( 'Malaysian Ringgits (MYR)', 'stripe-payments' ), 'RM' ),
			'MXN' => array( __( 'Mexican Peso (MXN)', 'stripe-payments' ), 'MX$' ),
			'NZD' => array( __( 'New Zealand Dollar (NZD)', 'stripe-payments' ), 'NZ$' ),
			'NOK' => array( __( 'Norwegian Krone (NOK)', 'stripe-payments' ), 'kr' ),
			'PEN' => array( __( 'Peruvian Nuevo Sol (PEN)', 'stripe-payments' ), 'S/' ),
			'PHP' => array( __( 'Philippine Pesos (PHP)', 'stripe-payments' ), '₱' ),
			'PLN' => array( __( 'Polish Zloty (PLN)', 'stripe-payments' ), 'zł' ),
			'RON' => array( __( 'Romanian Leu (RON)', 'stripe-payments' ), 'lei' ),
			'RUB' => array( __( 'Russian Ruble (RUB)', 'stripe-payments' ), '₽' ),
			'SAR' => array( __( 'Saudi Riyal (SAR)', 'stripe-payments' ), 'ر.س' ),
			'SGD' => array( __( 'Singapore Dollar (SGD)', 'stripe-payments' ), 'SG$' ),
			'ZAR' => array( __( 'South African Rand (ZAR)', 'stripe-payments' ), 'R' ),
			'KRW' => array( __( 'South Korean Won (KRW)', 'stripe-payments' ), '₩' ),
			'SEK' => array( __( 'Swedish Krona (SEK)', 'stripe-payments' ), 'kr' ),
			'CHF' => array( __( 'Swiss Franc (CHF)', 'stripe-payments' ), 'CHF' ),
			'TWD' => array( __( 'Taiwan New Dollars (TWD)', 'stripe-payments' ), 'NT$' ),
			'THB' => array( __( 'Thai Baht (THB)', 'stripe-payments' ), '฿' ),
			'TRY' => array( __( 'Turkish Lira (TRY)', 'stripe-payments' ), '₺' ),
			'UYU' => array( __( 'Uruguayan Peso (UYU)', 'stripe-payments' ), '$U' ),
			'VND' => array( __( 'Vietnamese Dong (VND)', 'stripe-payments' ), '₫' ),
		);
		$opts       = get_option( 'AcceptStripePayments-settings' );
		if ( isset( $opts['custom_currency_symbols'] ) && is_array( $opts['custom_currency_symbols'] ) ) {
			$currencies = array_merge( $currencies, $opts['custom_currency_symbols'] );
		}

		return $currencies;
	}

	public static function mail( $to, $subj, $body, $headers, $do_not_schedule = false ) {
		$opts            = get_option( 'AcceptStripePayments-settings' );
		$schedule_result = false;
		if ( ! $do_not_schedule && isset( $opts['enable_email_schedule'] ) && $opts['enable_email_schedule'] ) {
			$schedule_result = wp_schedule_single_event( time() - 10, 'asp_send_scheduled_email', array( $to, $subj, $body, $headers ) );
		}
		if ( ! $schedule_result ) {
			// can't schedule event for email notification. Let's send email without scheduling
			wp_mail( $to, $subj, $body, $headers );
		}
		return $schedule_result;
	}

	public static function send_error_email( $body ) {
		$opt     = get_option( 'AcceptStripePayments-settings' );
		$to      = $opt['send_email_on_error_to'];
		$from    = get_option( 'admin_email' );
		$headers = 'From: ' . $from . "\r\n";
		$subj    = __( 'Stripe Payments Error Details', 'stripe-payments' );

		//Add a general note to the error email adding more explanation to the site admin as to what this error email means.
		$general_note_for_error_email  = __( 'Note: It is normal for transaction errors like this to happen. For example - if a customer enters an incorrect card number or an expired card details, it will trigger an error.', 'stripe-payments' ) . "\r\n";
		$general_note_for_error_email .= __( 'The customer will be requested to enter valid details for the transaction to proceed.', 'stripe-payments' ) . "\r\n";
		$general_note_for_error_email .= __( 'This email contains some raw transaction data just for the site admin to be aware of the incident.', 'stripe-payments' ) . "\r\n";
		$general_note_for_error_email .= '-----' . "\r\n\r\n";

		$body = $general_note_for_error_email . $body;

		$schedule_result = ASP_Utils::mail( $to, $subj, $body, $headers, true );
		ASP_Debug_Logger::log( 'Error email sent to ' . $to . ', from email address used: ' . $from );
	}

	public static function get_small_product_thumb( $prod_id, $force_regen = false ) {
		$ret = '';
		//check if we have a thumbnail
		$curr_thumb = get_post_meta( $prod_id, 'asp_product_thumbnail', true );
		if ( empty( $curr_thumb ) ) {
			return $ret;
		}
		$ret = $curr_thumb;
		//check if we have 100x100 preview generated
		$thumb_thumb = get_post_meta( $prod_id, 'asp_product_thumbnail_thumb', true );
		if ( empty( $thumb_thumb ) || $force_regen ) {
			//looks like we don't have one. Let's generate it
			$thumb_thumb = '';
			$image       = wp_get_image_editor( $curr_thumb );
			if ( ! is_wp_error( $image ) ) {
				$image->resize( 100, 100, true );
				$upload_dir = wp_upload_dir();
				$ext        = pathinfo( $curr_thumb, PATHINFO_EXTENSION );
				$file_name  = 'asp_product_' . $prod_id . '_thumb_' . md5( $curr_thumb ) . '.' . $ext;
				$res        = $image->save( $upload_dir['path'] . '/' . $file_name );
				if ( ! is_wp_error( $res ) ) {
					$thumb_thumb = $upload_dir['url'] . '/' . $file_name;
				} else {
					//error saving thumb image
					return $ret;
				}
			} else {
				//error occurred during image load
				return $ret;
			}
			update_post_meta( $prod_id, 'asp_product_thumbnail_thumb', $thumb_thumb );
			$ret = $thumb_thumb;
		} else {
			// we have one. Let's return it
			$ret = $thumb_thumb;
		}
		if ( is_ssl() ) {
			$ret = self::url_to_https( $ret );
		}
		return $ret;
	}

	public static function formatted_price( $price, $curr = '', $price_is_cents = false ) {
		if ( empty( $price ) ) {
			$price = 0;
		}

		$opts = get_option( 'AcceptStripePayments-settings' );

		if ( false === $curr ) {
			//if curr set to false, we format price without currency symbol or code
			$curr_sym = '';
		} else {

			if ( '' === $curr ) {
				//if currency not specified, let's use default currency set in options
				$curr = $opts['currency_code'];
			}

			$curr = strtoupper( $curr );

			$currencies = self::get_currencies();
			if ( isset( $currencies[ $curr ] ) ) {
				$curr_sym = $currencies[ $curr ][1];
			} else {
				//no currency code found, let's just use currency code instead of symbol
				$curr_sym = $curr;
			}
		}

		//check if price is in cents
		if ( $price_is_cents && ! AcceptStripePayments::is_zero_cents( $curr ) ) {
			$price = intval( $price ) / 100;
		}

		$out = number_format( $price, $opts['price_decimals_num'], $opts['price_decimal_sep'], $opts['price_thousand_sep'] );

		switch ( $opts['price_currency_pos'] ) {
			case 'left':
				$out = $curr_sym . '' . $out;
				break;
			case 'right':
				$out .= '' . $curr_sym;
				break;
			default:
				$out .= '' . $curr_sym;
				break;
		}

		return $out;
	}

	public static function get_visitor_preferred_lang() {
		$langs = array();
		preg_match_all( '~([\w-]+)(?:[^,\d]+([\d.]+))?~', strtolower( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ), $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {

			list($a, $b) = explode( '-', $match[1] ) + array( '', '' );
			$value       = isset( $match[2] ) ? (float) $match[2] : 1.0;

				$langs[ $match[1] ] = $value;

				$langs[ $a ] = $value - 0.1;
		}
		arsort( $langs );

		if ( empty( $langs ) ) {
			return '';
		}

		reset( $langs );
		$lang = key( $langs );

		if ( strlen( $lang ) < 2 && strlen( $lang ) > 6 ) {
			return '';
		}

		if ( strlen( $lang ) >= 5 ) {
			$lang_parts = explode( '-', $lang );
			if ( 2 === count( $lang_parts ) ) {
				$lang = $lang_parts[0] . '_' . strtoupper( $lang_parts[1] );
			}
		}

		return $lang;
	}

	public static function load_custom_lang( $lang ) {
		global $l10n;
		$textdomain = 'stripe-payments';

		if ( isset( $l10n[ $textdomain ] ) ) {
			self::$textdomain_backup = $l10n[ $textdomain ];
		}

		$mo_file = '';

		foreach ( self::$textdomain_paths as $path ) {
			if ( file_exists( $path . $textdomain . '-' . $lang . '.mo' ) ) {
				$mo_file = $path . $textdomain . '-' . $lang . '.mo';
				break;
			}
		}

		if ( empty( $mo_file ) ) {
			return;
		}

		load_textdomain( $textdomain, $mo_file );
	}

	public static function set_custom_lang_if_needed() {
		$asp_class = AcceptStripePayments::get_instance();
		$lang      = $asp_class->get_setting( 'checkout_lang' );

		if ( empty( $lang ) ) {
			$lang = self::get_visitor_preferred_lang();
		} else {
			if ( isset( self::$lang_code_locale[ $lang ] ) ) {
				$lang = self::$lang_code_locale[ $lang ];
			}
		}

		self::load_custom_lang( $lang );
	}

	public static function load_stripe_lib() {
		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			require_once WP_ASP_PLUGIN_PATH . 'includes/stripe/init.php';
			\Stripe\Stripe::setAppInfo( 'Stripe Payments', WP_ASP_PLUGIN_VERSION, 'https://wordpress.org/plugins/stripe-payments/', 'pp_partner_Fvas9OJ0jQ2oNQ' );
			\Stripe\Stripe::setApiVersion( ASPMain::$stripe_api_ver );
		} else {
			$declared = new \ReflectionClass( '\Stripe\Stripe' );
			$path     = $declared->getFileName();
			$own_path = WP_ASP_PLUGIN_PATH . 'includes/stripe/lib/Stripe.php';
			if ( strtolower( $path ) !== strtolower( $own_path ) ) {
				// Stripe library is loaded from other location
				// Let's only log one warning per 6 hours in order to not flood the log
				$lib_warning_last_logged_time = get_option( 'asp_lib_warning_last_logged_time' );
				$time                         = time();
				if ( $time - ( 60 * 60 * 6 ) > $lib_warning_last_logged_time ) {
					$opts = get_option( 'AcceptStripePayments-settings' );
					if ( $opts['debug_log_enable'] ) {
						ASP_Debug_Logger::log( sprintf( "WARNING: Stripe PHP library conflict! Another Stripe PHP SDK library is being used. Please disable plugin or theme that provides it as it can cause issues during payment process.\r\nLibrary path: %s", $path ) );
						update_option( 'asp_lib_warning_last_logged_time', $time );
					}
				}
			}
		}
	}

	public static function gen_help_popup( $contents ) {
		return '<div class="wp-asp-help"><i class="dashicons dashicons-editor-help"></i><div class="wp-asp-help-text">' . $contents . '</div></div>';
	}

	private static function generate_ckey() {
		return md5( uniqid() );
	}

	public static function get_ckey( $regen = false ) {
		$ckey = get_option( 'asp_cache_key' );

		if ( empty( $ckey ) || $regen ) {
			$ckey = self::generate_ckey();
			update_option( 'asp_cache_key', $ckey );
		}
		return $ckey;

	}

	public static function url_to_https( $url ) {
		return preg_replace( '/^http:\/\//i', 'https://', $url );
	}

	public static function use_internal_api() {
		$asp_class               = AcceptStripePayments::get_instance();
		$dont_use_stripe_php_sdk = $asp_class->get_setting( 'dont_use_stripe_php_sdk' );

		if ( $dont_use_stripe_php_sdk ) {
			return true;
		}
		return false;
	}

	public static function get_visitor_token( $str = '' ) {
		$ua = ! empty( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';

		return md5( $ua . $str );
	}

	public static function clear_external_caches() {
		//WP Rocket
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		// wp-super-cache
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		// WPEngine
		if ( class_exists( 'WpeCommon' ) ) {
			WpeCommon::purge_memcached();
			WpeCommon::clear_maxcdn_cache();
			WpeCommon::purge_varnish_cache();
		}

		// W3 Total Cache
		if ( function_exists( 'w3tc_pgcache_flush' ) ) {
			w3tc_pgcache_flush();
		}

		// SG Optimizer
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}

		do_action( 'asp_clear_external_caches' );
	}

	public static function get_base_pp_url() {
		$base_url  = '';
		$structure = get_option( 'permalink_structure' );
		if ( empty( $structure ) ) {
			$home_url = get_home_url( null, '/' );
			$base_url = add_query_arg(
				array(
					'asp_action' => 'show_pp',
				),
				$home_url
			);
		} else {
			$base_url = get_home_url( null, AcceptStripePayments::$pp_slug . '/' );
		}
		return $base_url;
	}

	/**
	 * Returns Stripe account info
	 *
	 * Since 2.0.47
	 * @return mixed
	 */
	public static function get_stripe_acc_info() {
		$acc_info = false;

		$asp_main = AcceptStripePayments::get_instance();

		$key = $asp_main->is_live ? $asp_main->APISecKey : $asp_main->APISecKeyTest;

		try {
			if ( self::use_internal_api() ) {
				$api = ASP_Stripe_API::get_instance();
				$api->set_api_key( $key );
				$api->set_param( 'throw_exception', true );

				$acc_info = $api->get( 'account' );
			} else {
				ASP_Utils::load_stripe_lib();
				\Stripe\Stripe::setApiKey( $key );

				$acc_info = \Stripe\Account::retrieve();
			}
		} catch ( \Throwable $e ) {
			// handle error if needed
		}

		return $acc_info;
	}
}
