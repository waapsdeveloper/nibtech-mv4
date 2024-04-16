<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CountryTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('country')->delete();
        
        \DB::table('country')->insert(array (
            0 => 
            array (
                'id' => 1,
                'title' => 'Afghanistan',
                'code' => 'AF',
                'dialingcode' => '93',
            ),
            1 => 
            array (
                'id' => 2,
                'title' => 'Albania',
                'code' => 'AL',
                'dialingcode' => '355',
            ),
            2 => 
            array (
                'id' => 3,
                'title' => 'Algeria',
                'code' => 'DZ',
                'dialingcode' => '213',
            ),
            3 => 
            array (
                'id' => 4,
                'title' => 'American Samoa',
                'code' => 'AS',
                'dialingcode' => '1684',
            ),
            4 => 
            array (
                'id' => 5,
                'title' => 'Andorra',
                'code' => 'AD',
                'dialingcode' => '376',
            ),
            5 => 
            array (
                'id' => 6,
                'title' => 'Angola',
                'code' => 'AO',
                'dialingcode' => '244',
            ),
            6 => 
            array (
                'id' => 7,
                'title' => 'Anguilla',
                'code' => 'AI',
                'dialingcode' => '1264',
            ),
            7 => 
            array (
                'id' => 8,
                'title' => 'Antarctica',
                'code' => 'AQ',
                'dialingcode' => '0',
            ),
            8 => 
            array (
                'id' => 9,
                'title' => 'Antigua and Barbuda',
                'code' => 'AG',
                'dialingcode' => '1268',
            ),
            9 => 
            array (
                'id' => 10,
                'title' => 'Argentina',
                'code' => 'AR',
                'dialingcode' => '54',
            ),
            10 => 
            array (
                'id' => 11,
                'title' => 'Armenia',
                'code' => 'AM',
                'dialingcode' => '374',
            ),
            11 => 
            array (
                'id' => 12,
                'title' => 'Aruba',
                'code' => 'AW',
                'dialingcode' => '297',
            ),
            12 => 
            array (
                'id' => 13,
                'title' => 'Australia',
                'code' => 'AU',
                'dialingcode' => '61',
            ),
            13 => 
            array (
                'id' => 14,
                'title' => 'Austria',
                'code' => 'AT',
                'dialingcode' => '43',
            ),
            14 => 
            array (
                'id' => 15,
                'title' => 'Azerbaijan',
                'code' => 'AZ',
                'dialingcode' => '994',
            ),
            15 => 
            array (
                'id' => 16,
                'title' => 'Bahamas',
                'code' => 'BS',
                'dialingcode' => '1242',
            ),
            16 => 
            array (
                'id' => 17,
                'title' => 'Bahrain',
                'code' => 'BH',
                'dialingcode' => '973',
            ),
            17 => 
            array (
                'id' => 18,
                'title' => 'Bangladesh',
                'code' => 'BD',
                'dialingcode' => '880',
            ),
            18 => 
            array (
                'id' => 19,
                'title' => 'Barbados',
                'code' => 'BB',
                'dialingcode' => '1246',
            ),
            19 => 
            array (
                'id' => 20,
                'title' => 'Belarus',
                'code' => 'BY',
                'dialingcode' => '375',
            ),
            20 => 
            array (
                'id' => 21,
                'title' => 'Belgium',
                'code' => 'BE',
                'dialingcode' => '32',
            ),
            21 => 
            array (
                'id' => 22,
                'title' => 'Belize',
                'code' => 'BZ',
                'dialingcode' => '501',
            ),
            22 => 
            array (
                'id' => 23,
                'title' => 'Benin',
                'code' => 'BJ',
                'dialingcode' => '229',
            ),
            23 => 
            array (
                'id' => 24,
                'title' => 'Bermuda',
                'code' => 'BM',
                'dialingcode' => '1441',
            ),
            24 => 
            array (
                'id' => 25,
                'title' => 'Bhutan',
                'code' => 'BT',
                'dialingcode' => '975',
            ),
            25 => 
            array (
                'id' => 26,
                'title' => 'Bolivia',
                'code' => 'BO',
                'dialingcode' => '591',
            ),
            26 => 
            array (
                'id' => 27,
                'title' => 'Bosnia and Herzegovina',
                'code' => 'BA',
                'dialingcode' => '387',
            ),
            27 => 
            array (
                'id' => 28,
                'title' => 'Botswana',
                'code' => 'BW',
                'dialingcode' => '267',
            ),
            28 => 
            array (
                'id' => 29,
                'title' => 'Bouvet Island',
                'code' => 'BV',
                'dialingcode' => '0',
            ),
            29 => 
            array (
                'id' => 30,
                'title' => 'Brazil',
                'code' => 'BR',
                'dialingcode' => '55',
            ),
            30 => 
            array (
                'id' => 31,
                'title' => 'British Indian Ocean Territory',
                'code' => 'IO',
                'dialingcode' => '246',
            ),
            31 => 
            array (
                'id' => 32,
                'title' => 'Brunei Darussalam',
                'code' => 'BN',
                'dialingcode' => '673',
            ),
            32 => 
            array (
                'id' => 33,
                'title' => 'Bulgaria',
                'code' => 'BG',
                'dialingcode' => '359',
            ),
            33 => 
            array (
                'id' => 34,
                'title' => 'Burkina Faso',
                'code' => 'BF',
                'dialingcode' => '226',
            ),
            34 => 
            array (
                'id' => 35,
                'title' => 'Burundi',
                'code' => 'BI',
                'dialingcode' => '257',
            ),
            35 => 
            array (
                'id' => 36,
                'title' => 'Cambodia',
                'code' => 'KH',
                'dialingcode' => '855',
            ),
            36 => 
            array (
                'id' => 37,
                'title' => 'Cameroon',
                'code' => 'CM',
                'dialingcode' => '237',
            ),
            37 => 
            array (
                'id' => 38,
                'title' => 'Canada',
                'code' => 'CA',
                'dialingcode' => '1',
            ),
            38 => 
            array (
                'id' => 39,
                'title' => 'Cape Verde',
                'code' => 'CV',
                'dialingcode' => '238',
            ),
            39 => 
            array (
                'id' => 40,
                'title' => 'Cayman Islands',
                'code' => 'KY',
                'dialingcode' => '1345',
            ),
            40 => 
            array (
                'id' => 41,
                'title' => 'Central African Republic',
                'code' => 'CF',
                'dialingcode' => '236',
            ),
            41 => 
            array (
                'id' => 42,
                'title' => 'Chad',
                'code' => 'TD',
                'dialingcode' => '235',
            ),
            42 => 
            array (
                'id' => 43,
                'title' => 'Chile',
                'code' => 'CL',
                'dialingcode' => '56',
            ),
            43 => 
            array (
                'id' => 44,
                'title' => 'China',
                'code' => 'CN',
                'dialingcode' => '86',
            ),
            44 => 
            array (
                'id' => 45,
                'title' => 'Christmas Island',
                'code' => 'CX',
                'dialingcode' => '61',
            ),
            45 => 
            array (
                'id' => 46,
            'title' => 'Cocos (Keeling) Islands',
                'code' => 'CC',
                'dialingcode' => '672',
            ),
            46 => 
            array (
                'id' => 47,
                'title' => 'Colombia',
                'code' => 'CO',
                'dialingcode' => '57',
            ),
            47 => 
            array (
                'id' => 48,
                'title' => 'Comoros',
                'code' => 'KM',
                'dialingcode' => '269',
            ),
            48 => 
            array (
                'id' => 49,
                'title' => 'Congo',
                'code' => 'CG',
                'dialingcode' => '242',
            ),
            49 => 
            array (
                'id' => 50,
                'title' => 'Congo, the Democratic Republic of the',
                'code' => 'CD',
                'dialingcode' => '242',
            ),
            50 => 
            array (
                'id' => 51,
                'title' => 'Cook Islands',
                'code' => 'CK',
                'dialingcode' => '682',
            ),
            51 => 
            array (
                'id' => 52,
                'title' => 'Costa Rica',
                'code' => 'CR',
                'dialingcode' => '506',
            ),
            52 => 
            array (
                'id' => 53,
                'title' => 'Cote D\'Ivoire',
                'code' => 'CI',
                'dialingcode' => '225',
            ),
            53 => 
            array (
                'id' => 54,
                'title' => 'Croatia',
                'code' => 'HR',
                'dialingcode' => '385',
            ),
            54 => 
            array (
                'id' => 55,
                'title' => 'Cuba',
                'code' => 'CU',
                'dialingcode' => '53',
            ),
            55 => 
            array (
                'id' => 56,
                'title' => 'Cyprus',
                'code' => 'CY',
                'dialingcode' => '357',
            ),
            56 => 
            array (
                'id' => 57,
                'title' => 'Czech Republic',
                'code' => 'CZ',
                'dialingcode' => '420',
            ),
            57 => 
            array (
                'id' => 58,
                'title' => 'Denmark',
                'code' => 'DK',
                'dialingcode' => '45',
            ),
            58 => 
            array (
                'id' => 59,
                'title' => 'Djibouti',
                'code' => 'DJ',
                'dialingcode' => '253',
            ),
            59 => 
            array (
                'id' => 60,
                'title' => 'Dominica',
                'code' => 'DM',
                'dialingcode' => '1767',
            ),
            60 => 
            array (
                'id' => 61,
                'title' => 'Dominican Republic',
                'code' => 'DO',
                'dialingcode' => '1809',
            ),
            61 => 
            array (
                'id' => 62,
                'title' => 'Ecuador',
                'code' => 'EC',
                'dialingcode' => '593',
            ),
            62 => 
            array (
                'id' => 63,
                'title' => 'Egypt',
                'code' => 'EG',
                'dialingcode' => '20',
            ),
            63 => 
            array (
                'id' => 64,
                'title' => 'El Salvador',
                'code' => 'SV',
                'dialingcode' => '503',
            ),
            64 => 
            array (
                'id' => 65,
                'title' => 'Equatorial Guinea',
                'code' => 'GQ',
                'dialingcode' => '240',
            ),
            65 => 
            array (
                'id' => 66,
                'title' => 'Eritrea',
                'code' => 'ER',
                'dialingcode' => '291',
            ),
            66 => 
            array (
                'id' => 67,
                'title' => 'Estonia',
                'code' => 'EE',
                'dialingcode' => '372',
            ),
            67 => 
            array (
                'id' => 68,
                'title' => 'Ethiopia',
                'code' => 'ET',
                'dialingcode' => '251',
            ),
            68 => 
            array (
                'id' => 69,
            'title' => 'Falkland Islands (Malvinas)',
                'code' => 'FK',
                'dialingcode' => '500',
            ),
            69 => 
            array (
                'id' => 70,
                'title' => 'Faroe Islands',
                'code' => 'FO',
                'dialingcode' => '298',
            ),
            70 => 
            array (
                'id' => 71,
                'title' => 'Fiji',
                'code' => 'FJ',
                'dialingcode' => '679',
            ),
            71 => 
            array (
                'id' => 72,
                'title' => 'Finland',
                'code' => 'FI',
                'dialingcode' => '358',
            ),
            72 => 
            array (
                'id' => 73,
                'title' => 'France',
                'code' => 'FR',
                'dialingcode' => '33',
            ),
            73 => 
            array (
                'id' => 74,
                'title' => 'French Guiana',
                'code' => 'GF',
                'dialingcode' => '594',
            ),
            74 => 
            array (
                'id' => 75,
                'title' => 'French Polynesia',
                'code' => 'PF',
                'dialingcode' => '689',
            ),
            75 => 
            array (
                'id' => 76,
                'title' => 'French Southern Territories',
                'code' => 'TF',
                'dialingcode' => '0',
            ),
            76 => 
            array (
                'id' => 77,
                'title' => 'Gabon',
                'code' => 'GA',
                'dialingcode' => '241',
            ),
            77 => 
            array (
                'id' => 78,
                'title' => 'Gambia',
                'code' => 'GM',
                'dialingcode' => '220',
            ),
            78 => 
            array (
                'id' => 79,
                'title' => 'Georgia',
                'code' => 'GE',
                'dialingcode' => '995',
            ),
            79 => 
            array (
                'id' => 80,
                'title' => 'Germany',
                'code' => 'DE',
                'dialingcode' => '49',
            ),
            80 => 
            array (
                'id' => 81,
                'title' => 'Ghana',
                'code' => 'GH',
                'dialingcode' => '233',
            ),
            81 => 
            array (
                'id' => 82,
                'title' => 'Gibraltar',
                'code' => 'GI',
                'dialingcode' => '350',
            ),
            82 => 
            array (
                'id' => 83,
                'title' => 'Greece',
                'code' => 'GR',
                'dialingcode' => '30',
            ),
            83 => 
            array (
                'id' => 84,
                'title' => 'Greenland',
                'code' => 'GL',
                'dialingcode' => '299',
            ),
            84 => 
            array (
                'id' => 85,
                'title' => 'Grenada',
                'code' => 'GD',
                'dialingcode' => '1473',
            ),
            85 => 
            array (
                'id' => 86,
                'title' => 'Guadeloupe',
                'code' => 'GP',
                'dialingcode' => '590',
            ),
            86 => 
            array (
                'id' => 87,
                'title' => 'Guam',
                'code' => 'GU',
                'dialingcode' => '1671',
            ),
            87 => 
            array (
                'id' => 88,
                'title' => 'Guatemala',
                'code' => 'GT',
                'dialingcode' => '502',
            ),
            88 => 
            array (
                'id' => 89,
                'title' => 'Guinea',
                'code' => 'GN',
                'dialingcode' => '224',
            ),
            89 => 
            array (
                'id' => 90,
                'title' => 'Guinea-Bissau',
                'code' => 'GW',
                'dialingcode' => '245',
            ),
            90 => 
            array (
                'id' => 91,
                'title' => 'Guyana',
                'code' => 'GY',
                'dialingcode' => '592',
            ),
            91 => 
            array (
                'id' => 92,
                'title' => 'Haiti',
                'code' => 'HT',
                'dialingcode' => '509',
            ),
            92 => 
            array (
                'id' => 93,
                'title' => 'Heard Island and Mcdonald Islands',
                'code' => 'HM',
                'dialingcode' => '0',
            ),
            93 => 
            array (
                'id' => 94,
            'title' => 'Holy See (Vatican City State)',
                'code' => 'VA',
                'dialingcode' => '39',
            ),
            94 => 
            array (
                'id' => 95,
                'title' => 'Honduras',
                'code' => 'HN',
                'dialingcode' => '504',
            ),
            95 => 
            array (
                'id' => 96,
                'title' => 'Hong Kong',
                'code' => 'HK',
                'dialingcode' => '852',
            ),
            96 => 
            array (
                'id' => 97,
                'title' => 'Hungary',
                'code' => 'HU',
                'dialingcode' => '36',
            ),
            97 => 
            array (
                'id' => 98,
                'title' => 'Iceland',
                'code' => 'IS',
                'dialingcode' => '354',
            ),
            98 => 
            array (
                'id' => 99,
                'title' => 'India',
                'code' => 'IN',
                'dialingcode' => '91',
            ),
            99 => 
            array (
                'id' => 100,
                'title' => 'Indonesia',
                'code' => 'ID',
                'dialingcode' => '62',
            ),
            100 => 
            array (
                'id' => 101,
                'title' => 'Iran, Islamic Republic of',
                'code' => 'IR',
                'dialingcode' => '98',
            ),
            101 => 
            array (
                'id' => 102,
                'title' => 'Iraq',
                'code' => 'IQ',
                'dialingcode' => '964',
            ),
            102 => 
            array (
                'id' => 103,
                'title' => 'Ireland',
                'code' => 'IE',
                'dialingcode' => '353',
            ),
            103 => 
            array (
                'id' => 104,
                'title' => 'Israel',
                'code' => 'IL',
                'dialingcode' => '972',
            ),
            104 => 
            array (
                'id' => 105,
                'title' => 'Italy',
                'code' => 'IT',
                'dialingcode' => '39',
            ),
            105 => 
            array (
                'id' => 106,
                'title' => 'Jamaica',
                'code' => 'JM',
                'dialingcode' => '1876',
            ),
            106 => 
            array (
                'id' => 107,
                'title' => 'Japan',
                'code' => 'JP',
                'dialingcode' => '81',
            ),
            107 => 
            array (
                'id' => 108,
                'title' => 'Jordan',
                'code' => 'JO',
                'dialingcode' => '962',
            ),
            108 => 
            array (
                'id' => 109,
                'title' => 'Kazakhstan',
                'code' => 'KZ',
                'dialingcode' => '7',
            ),
            109 => 
            array (
                'id' => 110,
                'title' => 'Kenya',
                'code' => 'KE',
                'dialingcode' => '254',
            ),
            110 => 
            array (
                'id' => 111,
                'title' => 'Kiribati',
                'code' => 'KI',
                'dialingcode' => '686',
            ),
            111 => 
            array (
                'id' => 112,
                'title' => 'Korea, Democratic People\'s Republic of',
                'code' => 'KP',
                'dialingcode' => '850',
            ),
            112 => 
            array (
                'id' => 113,
                'title' => 'Korea, Republic of',
                'code' => 'KR',
                'dialingcode' => '82',
            ),
            113 => 
            array (
                'id' => 114,
                'title' => 'Kuwait',
                'code' => 'KW',
                'dialingcode' => '965',
            ),
            114 => 
            array (
                'id' => 115,
                'title' => 'Kyrgyzstan',
                'code' => 'KG',
                'dialingcode' => '996',
            ),
            115 => 
            array (
                'id' => 116,
                'title' => 'Lao People\'s Democratic Republic',
                'code' => 'LA',
                'dialingcode' => '856',
            ),
            116 => 
            array (
                'id' => 117,
                'title' => 'Latvia',
                'code' => 'LV',
                'dialingcode' => '371',
            ),
            117 => 
            array (
                'id' => 118,
                'title' => 'Lebanon',
                'code' => 'LB',
                'dialingcode' => '961',
            ),
            118 => 
            array (
                'id' => 119,
                'title' => 'Lesotho',
                'code' => 'LS',
                'dialingcode' => '266',
            ),
            119 => 
            array (
                'id' => 120,
                'title' => 'Liberia',
                'code' => 'LR',
                'dialingcode' => '231',
            ),
            120 => 
            array (
                'id' => 121,
                'title' => 'Libyan Arab Jamahiriya',
                'code' => 'LY',
                'dialingcode' => '218',
            ),
            121 => 
            array (
                'id' => 122,
                'title' => 'Liechtenstein',
                'code' => 'LI',
                'dialingcode' => '423',
            ),
            122 => 
            array (
                'id' => 123,
                'title' => 'Lithuania',
                'code' => 'LT',
                'dialingcode' => '370',
            ),
            123 => 
            array (
                'id' => 124,
                'title' => 'Luxembourg',
                'code' => 'LU',
                'dialingcode' => '352',
            ),
            124 => 
            array (
                'id' => 125,
                'title' => 'Macao',
                'code' => 'MO',
                'dialingcode' => '853',
            ),
            125 => 
            array (
                'id' => 126,
                'title' => 'Macedonia, the Former Yugoslav Republic of',
                'code' => 'MK',
                'dialingcode' => '389',
            ),
            126 => 
            array (
                'id' => 127,
                'title' => 'Madagascar',
                'code' => 'MG',
                'dialingcode' => '261',
            ),
            127 => 
            array (
                'id' => 128,
                'title' => 'Malawi',
                'code' => 'MW',
                'dialingcode' => '265',
            ),
            128 => 
            array (
                'id' => 129,
                'title' => 'Malaysia',
                'code' => 'MY',
                'dialingcode' => '60',
            ),
            129 => 
            array (
                'id' => 130,
                'title' => 'Maldives',
                'code' => 'MV',
                'dialingcode' => '960',
            ),
            130 => 
            array (
                'id' => 131,
                'title' => 'Mali',
                'code' => 'ML',
                'dialingcode' => '223',
            ),
            131 => 
            array (
                'id' => 132,
                'title' => 'Malta',
                'code' => 'MT',
                'dialingcode' => '356',
            ),
            132 => 
            array (
                'id' => 133,
                'title' => 'Marshall Islands',
                'code' => 'MH',
                'dialingcode' => '692',
            ),
            133 => 
            array (
                'id' => 134,
                'title' => 'Martinique',
                'code' => 'MQ',
                'dialingcode' => '596',
            ),
            134 => 
            array (
                'id' => 135,
                'title' => 'Mauritania',
                'code' => 'MR',
                'dialingcode' => '222',
            ),
            135 => 
            array (
                'id' => 136,
                'title' => 'Mauritius',
                'code' => 'MU',
                'dialingcode' => '230',
            ),
            136 => 
            array (
                'id' => 137,
                'title' => 'Mayotte',
                'code' => 'YT',
                'dialingcode' => '269',
            ),
            137 => 
            array (
                'id' => 138,
                'title' => 'Mexico',
                'code' => 'MX',
                'dialingcode' => '52',
            ),
            138 => 
            array (
                'id' => 139,
                'title' => 'Micronesia, Federated States of',
                'code' => 'FM',
                'dialingcode' => '691',
            ),
            139 => 
            array (
                'id' => 140,
                'title' => 'Moldova, Republic of',
                'code' => 'MD',
                'dialingcode' => '373',
            ),
            140 => 
            array (
                'id' => 141,
                'title' => 'Monaco',
                'code' => 'MC',
                'dialingcode' => '377',
            ),
            141 => 
            array (
                'id' => 142,
                'title' => 'Mongolia',
                'code' => 'MN',
                'dialingcode' => '976',
            ),
            142 => 
            array (
                'id' => 143,
                'title' => 'Montserrat',
                'code' => 'MS',
                'dialingcode' => '1664',
            ),
            143 => 
            array (
                'id' => 144,
                'title' => 'Morocco',
                'code' => 'MA',
                'dialingcode' => '212',
            ),
            144 => 
            array (
                'id' => 145,
                'title' => 'Mozambique',
                'code' => 'MZ',
                'dialingcode' => '258',
            ),
            145 => 
            array (
                'id' => 146,
                'title' => 'Myanmar',
                'code' => 'MM',
                'dialingcode' => '95',
            ),
            146 => 
            array (
                'id' => 147,
                'title' => 'Namibia',
                'code' => 'NA',
                'dialingcode' => '264',
            ),
            147 => 
            array (
                'id' => 148,
                'title' => 'Nauru',
                'code' => 'NR',
                'dialingcode' => '674',
            ),
            148 => 
            array (
                'id' => 149,
                'title' => 'Nepal',
                'code' => 'NP',
                'dialingcode' => '977',
            ),
            149 => 
            array (
                'id' => 150,
                'title' => 'Netherlands',
                'code' => 'NL',
                'dialingcode' => '31',
            ),
            150 => 
            array (
                'id' => 151,
                'title' => 'Netherlands Antilles',
                'code' => 'AN',
                'dialingcode' => '599',
            ),
            151 => 
            array (
                'id' => 152,
                'title' => 'New Caledonia',
                'code' => 'NC',
                'dialingcode' => '687',
            ),
            152 => 
            array (
                'id' => 153,
                'title' => 'New Zealand',
                'code' => 'NZ',
                'dialingcode' => '64',
            ),
            153 => 
            array (
                'id' => 154,
                'title' => 'Nicaragua',
                'code' => 'NI',
                'dialingcode' => '505',
            ),
            154 => 
            array (
                'id' => 155,
                'title' => 'Niger',
                'code' => 'NE',
                'dialingcode' => '227',
            ),
            155 => 
            array (
                'id' => 156,
                'title' => 'Nigeria',
                'code' => 'NG',
                'dialingcode' => '234',
            ),
            156 => 
            array (
                'id' => 157,
                'title' => 'Niue',
                'code' => 'NU',
                'dialingcode' => '683',
            ),
            157 => 
            array (
                'id' => 158,
                'title' => 'Norfolk Island',
                'code' => 'NF',
                'dialingcode' => '672',
            ),
            158 => 
            array (
                'id' => 159,
                'title' => 'Northern Mariana Islands',
                'code' => 'MP',
                'dialingcode' => '1670',
            ),
            159 => 
            array (
                'id' => 160,
                'title' => 'Norway',
                'code' => 'NO',
                'dialingcode' => '47',
            ),
            160 => 
            array (
                'id' => 161,
                'title' => 'Oman',
                'code' => 'OM',
                'dialingcode' => '968',
            ),
            161 => 
            array (
                'id' => 162,
                'title' => 'Pakistan',
                'code' => 'PK',
                'dialingcode' => '92',
            ),
            162 => 
            array (
                'id' => 163,
                'title' => 'Palau',
                'code' => 'PW',
                'dialingcode' => '680',
            ),
            163 => 
            array (
                'id' => 164,
                'title' => 'Palestinian Territory, Occupied',
                'code' => 'PS',
                'dialingcode' => '970',
            ),
            164 => 
            array (
                'id' => 165,
                'title' => 'Panama',
                'code' => 'PA',
                'dialingcode' => '507',
            ),
            165 => 
            array (
                'id' => 166,
                'title' => 'Papua New Guinea',
                'code' => 'PG',
                'dialingcode' => '675',
            ),
            166 => 
            array (
                'id' => 167,
                'title' => 'Paraguay',
                'code' => 'PY',
                'dialingcode' => '595',
            ),
            167 => 
            array (
                'id' => 168,
                'title' => 'Peru',
                'code' => 'PE',
                'dialingcode' => '51',
            ),
            168 => 
            array (
                'id' => 169,
                'title' => 'Philippines',
                'code' => 'PH',
                'dialingcode' => '63',
            ),
            169 => 
            array (
                'id' => 170,
                'title' => 'Pitcairn',
                'code' => 'PN',
                'dialingcode' => '0',
            ),
            170 => 
            array (
                'id' => 171,
                'title' => 'Poland',
                'code' => 'PL',
                'dialingcode' => '48',
            ),
            171 => 
            array (
                'id' => 172,
                'title' => 'Portugal',
                'code' => 'PT',
                'dialingcode' => '351',
            ),
            172 => 
            array (
                'id' => 173,
                'title' => 'Puerto Rico',
                'code' => 'PR',
                'dialingcode' => '1787',
            ),
            173 => 
            array (
                'id' => 174,
                'title' => 'Qatar',
                'code' => 'QA',
                'dialingcode' => '974',
            ),
            174 => 
            array (
                'id' => 175,
                'title' => 'Reunion',
                'code' => 'RE',
                'dialingcode' => '262',
            ),
            175 => 
            array (
                'id' => 176,
                'title' => 'Romania',
                'code' => 'RO',
                'dialingcode' => '40',
            ),
            176 => 
            array (
                'id' => 177,
                'title' => 'Russian Federation',
                'code' => 'RU',
                'dialingcode' => '70',
            ),
            177 => 
            array (
                'id' => 178,
                'title' => 'Rwanda',
                'code' => 'RW',
                'dialingcode' => '250',
            ),
            178 => 
            array (
                'id' => 179,
                'title' => 'Saint Helena',
                'code' => 'SH',
                'dialingcode' => '290',
            ),
            179 => 
            array (
                'id' => 180,
                'title' => 'Saint Kitts and Nevis',
                'code' => 'KN',
                'dialingcode' => '1869',
            ),
            180 => 
            array (
                'id' => 181,
                'title' => 'Saint Lucia',
                'code' => 'LC',
                'dialingcode' => '1758',
            ),
            181 => 
            array (
                'id' => 182,
                'title' => 'Saint Pierre and Miquelon',
                'code' => 'PM',
                'dialingcode' => '508',
            ),
            182 => 
            array (
                'id' => 183,
                'title' => 'Saint Vincent and the Grenadines',
                'code' => 'VC',
                'dialingcode' => '1784',
            ),
            183 => 
            array (
                'id' => 184,
                'title' => 'Samoa',
                'code' => 'WS',
                'dialingcode' => '684',
            ),
            184 => 
            array (
                'id' => 185,
                'title' => 'San Marino',
                'code' => 'SM',
                'dialingcode' => '378',
            ),
            185 => 
            array (
                'id' => 186,
                'title' => 'Sao Tome and Principe',
                'code' => 'ST',
                'dialingcode' => '239',
            ),
            186 => 
            array (
                'id' => 187,
                'title' => 'Saudi Arabia',
                'code' => 'SA',
                'dialingcode' => '966',
            ),
            187 => 
            array (
                'id' => 188,
                'title' => 'Senegal',
                'code' => 'SN',
                'dialingcode' => '221',
            ),
            188 => 
            array (
                'id' => 189,
                'title' => 'Serbia and Montenegro',
                'code' => 'CS',
                'dialingcode' => '381',
            ),
            189 => 
            array (
                'id' => 190,
                'title' => 'Seychelles',
                'code' => 'SC',
                'dialingcode' => '248',
            ),
            190 => 
            array (
                'id' => 191,
                'title' => 'Sierra Leone',
                'code' => 'SL',
                'dialingcode' => '232',
            ),
            191 => 
            array (
                'id' => 192,
                'title' => 'Singapore',
                'code' => 'SG',
                'dialingcode' => '65',
            ),
            192 => 
            array (
                'id' => 193,
                'title' => 'Slovakia',
                'code' => 'SK',
                'dialingcode' => '421',
            ),
            193 => 
            array (
                'id' => 194,
                'title' => 'Slovenia',
                'code' => 'SI',
                'dialingcode' => '386',
            ),
            194 => 
            array (
                'id' => 195,
                'title' => 'Solomon Islands',
                'code' => 'SB',
                'dialingcode' => '677',
            ),
            195 => 
            array (
                'id' => 196,
                'title' => 'Somalia',
                'code' => 'SO',
                'dialingcode' => '252',
            ),
            196 => 
            array (
                'id' => 197,
                'title' => 'South Africa',
                'code' => 'ZA',
                'dialingcode' => '27',
            ),
            197 => 
            array (
                'id' => 198,
                'title' => 'South Georgia and the South Sandwich Islands',
                'code' => 'GS',
                'dialingcode' => '0',
            ),
            198 => 
            array (
                'id' => 199,
                'title' => 'Spain',
                'code' => 'ES',
                'dialingcode' => '34',
            ),
            199 => 
            array (
                'id' => 200,
                'title' => 'Sri Lanka',
                'code' => 'LK',
                'dialingcode' => '94',
            ),
            200 => 
            array (
                'id' => 201,
                'title' => 'Sudan',
                'code' => 'SD',
                'dialingcode' => '249',
            ),
            201 => 
            array (
                'id' => 202,
                'title' => 'Suriname',
                'code' => 'SR',
                'dialingcode' => '597',
            ),
            202 => 
            array (
                'id' => 203,
                'title' => 'Svalbard and Jan Mayen',
                'code' => 'SJ',
                'dialingcode' => '47',
            ),
            203 => 
            array (
                'id' => 204,
                'title' => 'Swaziland',
                'code' => 'SZ',
                'dialingcode' => '268',
            ),
            204 => 
            array (
                'id' => 205,
                'title' => 'Sweden',
                'code' => 'SE',
                'dialingcode' => '46',
            ),
            205 => 
            array (
                'id' => 206,
                'title' => 'Switzerland',
                'code' => 'CH',
                'dialingcode' => '41',
            ),
            206 => 
            array (
                'id' => 207,
                'title' => 'Syrian Arab Republic',
                'code' => 'SY',
                'dialingcode' => '963',
            ),
            207 => 
            array (
                'id' => 208,
                'title' => 'Taiwan, Province of China',
                'code' => 'TW',
                'dialingcode' => '886',
            ),
            208 => 
            array (
                'id' => 209,
                'title' => 'Tajikistan',
                'code' => 'TJ',
                'dialingcode' => '992',
            ),
            209 => 
            array (
                'id' => 210,
                'title' => 'Tanzania, United Republic of',
                'code' => 'TZ',
                'dialingcode' => '255',
            ),
            210 => 
            array (
                'id' => 211,
                'title' => 'Thailand',
                'code' => 'TH',
                'dialingcode' => '66',
            ),
            211 => 
            array (
                'id' => 212,
                'title' => 'Timor-Leste',
                'code' => 'TL',
                'dialingcode' => '670',
            ),
            212 => 
            array (
                'id' => 213,
                'title' => 'Togo',
                'code' => 'TG',
                'dialingcode' => '228',
            ),
            213 => 
            array (
                'id' => 214,
                'title' => 'Tokelau',
                'code' => 'TK',
                'dialingcode' => '690',
            ),
            214 => 
            array (
                'id' => 215,
                'title' => 'Tonga',
                'code' => 'TO',
                'dialingcode' => '676',
            ),
            215 => 
            array (
                'id' => 216,
                'title' => 'Trinidad and Tobago',
                'code' => 'TT',
                'dialingcode' => '1868',
            ),
            216 => 
            array (
                'id' => 217,
                'title' => 'Tunisia',
                'code' => 'TN',
                'dialingcode' => '216',
            ),
            217 => 
            array (
                'id' => 218,
                'title' => 'Turkey',
                'code' => 'TR',
                'dialingcode' => '90',
            ),
            218 => 
            array (
                'id' => 219,
                'title' => 'Turkmenistan',
                'code' => 'TM',
                'dialingcode' => '7370',
            ),
            219 => 
            array (
                'id' => 220,
                'title' => 'Turks and Caicos Islands',
                'code' => 'TC',
                'dialingcode' => '1649',
            ),
            220 => 
            array (
                'id' => 221,
                'title' => 'Tuvalu',
                'code' => 'TV',
                'dialingcode' => '688',
            ),
            221 => 
            array (
                'id' => 222,
                'title' => 'Uganda',
                'code' => 'UG',
                'dialingcode' => '256',
            ),
            222 => 
            array (
                'id' => 223,
                'title' => 'Ukraine',
                'code' => 'UA',
                'dialingcode' => '380',
            ),
            223 => 
            array (
                'id' => 224,
                'title' => 'United Arab Emirates',
                'code' => 'AE',
                'dialingcode' => '971',
            ),
            224 => 
            array (
                'id' => 225,
                'title' => 'United Kingdom',
                'code' => 'GB',
                'dialingcode' => '44',
            ),
            225 => 
            array (
                'id' => 226,
                'title' => 'United States',
                'code' => 'US',
                'dialingcode' => '1',
            ),
            226 => 
            array (
                'id' => 227,
                'title' => 'United States Minor Outlying Islands',
                'code' => 'UM',
                'dialingcode' => '1',
            ),
            227 => 
            array (
                'id' => 228,
                'title' => 'Uruguay',
                'code' => 'UY',
                'dialingcode' => '598',
            ),
            228 => 
            array (
                'id' => 229,
                'title' => 'Uzbekistan',
                'code' => 'UZ',
                'dialingcode' => '998',
            ),
            229 => 
            array (
                'id' => 230,
                'title' => 'Vanuatu',
                'code' => 'VU',
                'dialingcode' => '678',
            ),
            230 => 
            array (
                'id' => 231,
                'title' => 'Venezuela',
                'code' => 'VE',
                'dialingcode' => '58',
            ),
            231 => 
            array (
                'id' => 232,
                'title' => 'Viet Nam',
                'code' => 'VN',
                'dialingcode' => '84',
            ),
            232 => 
            array (
                'id' => 233,
                'title' => 'Virgin Islands, British',
                'code' => 'VG',
                'dialingcode' => '1284',
            ),
            233 => 
            array (
                'id' => 234,
                'title' => 'Virgin Islands, U.s.',
                'code' => 'VI',
                'dialingcode' => '1340',
            ),
            234 => 
            array (
                'id' => 235,
                'title' => 'Wallis and Futuna',
                'code' => 'WF',
                'dialingcode' => '681',
            ),
            235 => 
            array (
                'id' => 236,
                'title' => 'Western Sahara',
                'code' => 'EH',
                'dialingcode' => '212',
            ),
            236 => 
            array (
                'id' => 237,
                'title' => 'Yemen',
                'code' => 'YE',
                'dialingcode' => '967',
            ),
            237 => 
            array (
                'id' => 238,
                'title' => 'Zambia',
                'code' => 'ZM',
                'dialingcode' => '260',
            ),
            238 => 
            array (
                'id' => 239,
                'title' => 'Zimbabwe',
                'code' => 'ZW',
                'dialingcode' => '263',
            ),
        ));
        
        
    }
}