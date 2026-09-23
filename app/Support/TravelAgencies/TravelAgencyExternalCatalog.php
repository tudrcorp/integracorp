<?php

declare(strict_types=1);

namespace App\Support\TravelAgencies;

/**
 * Identificadores externos de la hoja Agencias del listado de agencias de viaje.
 *
 * Las seis agencias del Excel que no existen en IntegraCorp no están aquí.
 * Las cuatro con nombre distinto traen el nombre actual de la tabla en match_names
 * y rename en true para reemplazarlo por el del Excel.
 */
final class TravelAgencyExternalCatalog
{
    /**
     * @return list<array{name: string, id_agencia: int, id_de_agente: string, rename: bool, match_names: list<string>}>
     */
    public static function rows(): array
    {
        return [
            0 => [
                'name' => 'A Donde Alirio Online',
                'id_agencia' => 958,
                'id_de_agente' => 'A344062',
                'rename' => false,
                'match_names' => [
                ],
            ],
            1 => [
                'name' => 'Adanel Viajes',
                'id_agencia' => 840,
                'id_de_agente' => 'A343944',
                'rename' => false,
                'match_names' => [
                ],
            ],
            2 => [
                'name' => 'Aerotravel Service,C.A',
                'id_agencia' => 813,
                'id_de_agente' => 'A343917',
                'rename' => false,
                'match_names' => [
                ],
            ],
            3 => [
                'name' => 'Afamia Tours',
                'id_agencia' => 900,
                'id_de_agente' => 'A344004',
                'rename' => false,
                'match_names' => [
                ],
            ],
            4 => [
                'name' => 'Afortunadas Tours',
                'id_agencia' => 798,
                'id_de_agente' => 'A343902',
                'rename' => false,
                'match_names' => [
                ],
            ],
            5 => [
                'name' => 'Agencia de Turismo Los Viajes del Rabi, C.A.',
                'id_agencia' => 822,
                'id_de_agente' => 'A343926',
                'rename' => false,
                'match_names' => [
                ],
            ],
            6 => [
                'name' => 'Agencia de Viajes Apolo,C.A',
                'id_agencia' => 896,
                'id_de_agente' => 'A344000',
                'rename' => false,
                'match_names' => [
                ],
            ],
            7 => [
                'name' => 'Agencia de Viajes Blue Paradise,C.A',
                'id_agencia' => 915,
                'id_de_agente' => 'A344019',
                'rename' => true,
                'match_names' => [
                    0 => 'Agencia de viajes Blue Paradise',
                ],
            ],
            8 => [
                'name' => 'Agencia de Viajes Di Blasio,C.A',
                'id_agencia' => 917,
                'id_de_agente' => 'A344021',
                'rename' => false,
                'match_names' => [
                ],
            ],
            9 => [
                'name' => 'Agencia De Viajes Dream Fly',
                'id_agencia' => 764,
                'id_de_agente' => 'A343868',
                'rename' => false,
                'match_names' => [
                ],
            ],
            10 => [
                'name' => 'Agencia de Viajes Figartour, C.A',
                'id_agencia' => 903,
                'id_de_agente' => 'A344007',
                'rename' => false,
                'match_names' => [
                ],
            ],
            11 => [
                'name' => 'Agencia de Viajes Nova,C.A',
                'id_agencia' => 895,
                'id_de_agente' => 'A343999',
                'rename' => false,
                'match_names' => [
                ],
            ],
            12 => [
                'name' => 'Agencia de Viajes Over Las Olas',
                'id_agencia' => 765,
                'id_de_agente' => 'A343869',
                'rename' => false,
                'match_names' => [
                ],
            ],
            13 => [
                'name' => 'AGENCIA DE VIAJES SAMARA',
                'id_agencia' => 932,
                'id_de_agente' => 'A344036',
                'rename' => false,
                'match_names' => [
                ],
            ],
            14 => [
                'name' => 'Agencia de Viajes Vive, C.A',
                'id_agencia' => 868,
                'id_de_agente' => 'A343972',
                'rename' => false,
                'match_names' => [
                ],
            ],
            15 => [
                'name' => 'Agencia de Viajes y Turismo Arab Tours C.A.',
                'id_agencia' => 772,
                'id_de_agente' => 'A343876',
                'rename' => false,
                'match_names' => [
                ],
            ],
            16 => [
                'name' => 'Agencia de Viajes y Turismo Gestión Turistica',
                'id_agencia' => 948,
                'id_de_agente' => 'A344052',
                'rename' => false,
                'match_names' => [
                ],
            ],
            17 => [
                'name' => 'Agencia de Viajes y Turismo Lusitana,C.A',
                'id_agencia' => 933,
                'id_de_agente' => 'A344037',
                'rename' => false,
                'match_names' => [
                ],
            ],
            18 => [
                'name' => 'Agencia Mundo Sol',
                'id_agencia' => 851,
                'id_de_agente' => 'A343955',
                'rename' => false,
                'match_names' => [
                ],
            ],
            19 => [
                'name' => 'Agencia Polymar Viajes y Turismo,C.A',
                'id_agencia' => 940,
                'id_de_agente' => 'A344044',
                'rename' => false,
                'match_names' => [
                ],
            ],
            20 => [
                'name' => 'Alborada Venezuela',
                'id_agencia' => 774,
                'id_de_agente' => 'A343878',
                'rename' => false,
                'match_names' => [
                ],
            ],
            21 => [
                'name' => 'Alpi Viajes,C.A',
                'id_agencia' => 934,
                'id_de_agente' => 'A344038',
                'rename' => false,
                'match_names' => [
                ],
            ],
            22 => [
                'name' => 'AMINTUR,C.A',
                'id_agencia' => 825,
                'id_de_agente' => 'A343929',
                'rename' => false,
                'match_names' => [
                ],
            ],
            23 => [
                'name' => 'Ani Tours Agencia de Viajes y Turismo',
                'id_agencia' => 829,
                'id_de_agente' => 'A343933',
                'rename' => false,
                'match_names' => [
                ],
            ],
            24 => [
                'name' => 'Antares Viajes',
                'id_agencia' => 951,
                'id_de_agente' => 'A344055',
                'rename' => false,
                'match_names' => [
                ],
            ],
            25 => [
                'name' => 'Argema Agencia de Viajes y Turismo',
                'id_agencia' => 757,
                'id_de_agente' => 'A343861',
                'rename' => false,
                'match_names' => [
                ],
            ],
            26 => [
                'name' => 'Artour Viajes',
                'id_agencia' => 955,
                'id_de_agente' => 'A344059',
                'rename' => false,
                'match_names' => [
                ],
            ],
            27 => [
                'name' => 'AsistViajes, C,A by Grupo  Zumaque',
                'id_agencia' => 792,
                'id_de_agente' => 'A343896',
                'rename' => false,
                'match_names' => [
                ],
            ],
            28 => [
                'name' => 'Atlas S.A',
                'id_agencia' => 797,
                'id_de_agente' => 'A343901',
                'rename' => false,
                'match_names' => [
                ],
            ],
            29 => [
                'name' => 'Atlas Travel',
                'id_agencia' => 709,
                'id_de_agente' => 'A343813',
                'rename' => false,
                'match_names' => [
                ],
            ],
            30 => [
                'name' => 'Aventour Marketing,C.A',
                'id_agencia' => 912,
                'id_de_agente' => 'A344016',
                'rename' => false,
                'match_names' => [
                ],
            ],
            31 => [
                'name' => 'Aventurismo Global C.A',
                'id_agencia' => 826,
                'id_de_agente' => 'A343930',
                'rename' => false,
                'match_names' => [
                ],
            ],
            32 => [
                'name' => 'Beltur,S.A',
                'id_agencia' => 818,
                'id_de_agente' => 'A343922',
                'rename' => false,
                'match_names' => [
                ],
            ],
            33 => [
                'name' => 'Betravelshop',
                'id_agencia' => 850,
                'id_de_agente' => 'A343954',
                'rename' => true,
                'match_names' => [
                    0 => 'Betravelshop Maracay, C.A',
                ],
            ],
            34 => [
                'name' => 'Bienvenido Travel SAS',
                'id_agencia' => 949,
                'id_de_agente' => 'A344053',
                'rename' => false,
                'match_names' => [
                ],
            ],
            35 => [
                'name' => 'Bleisure Travel',
                'id_agencia' => 800,
                'id_de_agente' => 'A343904',
                'rename' => false,
                'match_names' => [
                ],
            ],
            36 => [
                'name' => 'Bless Viajes',
                'id_agencia' => 802,
                'id_de_agente' => 'A343906',
                'rename' => false,
                'match_names' => [
                ],
            ],
            37 => [
                'name' => 'Boderline Tours, C.A',
                'id_agencia' => 755,
                'id_de_agente' => 'A343859',
                'rename' => false,
                'match_names' => [
                ],
            ],
            38 => [
                'name' => 'Candes Travel',
                'id_agencia' => 732,
                'id_de_agente' => 'A343836',
                'rename' => false,
                'match_names' => [
                ],
            ],
            39 => [
                'name' => 'Capitana Tours',
                'id_agencia' => 812,
                'id_de_agente' => 'A343916',
                'rename' => false,
                'match_names' => [
                ],
            ],
            40 => [
                'name' => 'Caracas Viajes',
                'id_agencia' => 883,
                'id_de_agente' => 'A343987',
                'rename' => false,
                'match_names' => [
                ],
            ],
            41 => [
                'name' => 'Carcelen Travel',
                'id_agencia' => 860,
                'id_de_agente' => 'A343964',
                'rename' => false,
                'match_names' => [
                ],
            ],
            42 => [
                'name' => 'Condor Verde Travel',
                'id_agencia' => 785,
                'id_de_agente' => 'A343889',
                'rename' => false,
                'match_names' => [
                ],
            ],
            43 => [
                'name' => 'Conkhep, C.A',
                'id_agencia' => 716,
                'id_de_agente' => 'A343820',
                'rename' => false,
                'match_names' => [
                ],
            ],
            44 => [
                'name' => 'Connection Tour',
                'id_agencia' => 824,
                'id_de_agente' => 'A343928',
                'rename' => false,
                'match_names' => [
                ],
            ],
            45 => [
                'name' => 'Conquer Ways',
                'id_agencia' => 735,
                'id_de_agente' => 'A343839',
                'rename' => false,
                'match_names' => [
                ],
            ],
            46 => [
                'name' => 'Cooperativa Waku Tours, RL',
                'id_agencia' => 908,
                'id_de_agente' => 'A344012',
                'rename' => false,
                'match_names' => [
                ],
            ],
            47 => [
                'name' => 'Corporación Salta, S.A.',
                'id_agencia' => 814,
                'id_de_agente' => 'A343918',
                'rename' => false,
                'match_names' => [
                ],
            ],
            48 => [
                'name' => 'D2 Tours',
                'id_agencia' => 990,
                'id_de_agente' => 'A344094',
                'rename' => false,
                'match_names' => [
                ],
            ],
            49 => [
                'name' => 'Daccord Tours Agencia de Viajes',
                'id_agencia' => 842,
                'id_de_agente' => 'A343946',
                'rename' => false,
                'match_names' => [
                ],
            ],
            50 => [
                'name' => 'Drumtrips – Viajes Y Turismo C.A',
                'id_agencia' => 821,
                'id_de_agente' => 'A343925',
                'rename' => false,
                'match_names' => [
                ],
            ],
            51 => [
                'name' => 'DTTravel',
                'id_agencia' => 945,
                'id_de_agente' => 'A344049',
                'rename' => false,
                'match_names' => [
                ],
            ],
            52 => [
                'name' => 'Easy Travel Venezuela',
                'id_agencia' => 816,
                'id_de_agente' => 'A343920',
                'rename' => false,
                'match_names' => [
                ],
            ],
            53 => [
                'name' => 'Ecocamping Venezuela C.A.',
                'id_agencia' => 890,
                'id_de_agente' => 'A343994',
                'rename' => false,
                'match_names' => [
                ],
            ],
            54 => [
                'name' => 'El Caribe 2020',
                'id_agencia' => 805,
                'id_de_agente' => 'A343909',
                'rename' => false,
                'match_names' => [
                ],
            ],
            55 => [
                'name' => 'Emporio Travel',
                'id_agencia' => 704,
                'id_de_agente' => 'A343808',
                'rename' => false,
                'match_names' => [
                ],
            ],
            56 => [
                'name' => 'Emy Tuy Viajes y Turismo',
                'id_agencia' => 943,
                'id_de_agente' => 'A344047',
                'rename' => false,
                'match_names' => [
                ],
            ],
            57 => [
                'name' => 'En la mira del Tigre Tours',
                'id_agencia' => 871,
                'id_de_agente' => 'A343975',
                'rename' => false,
                'match_names' => [
                ],
            ],
            58 => [
                'name' => 'Estratus Global',
                'id_agencia' => 911,
                'id_de_agente' => 'A344015',
                'rename' => false,
                'match_names' => [
                ],
            ],
            59 => [
                'name' => 'Evodia Viajes y Turismo',
                'id_agencia' => 819,
                'id_de_agente' => 'A343923',
                'rename' => false,
                'match_names' => [
                ],
            ],
            60 => [
                'name' => 'Evolucion Agencia',
                'id_agencia' => 751,
                'id_de_agente' => 'A343855',
                'rename' => false,
                'match_names' => [
                ],
            ],
            61 => [
                'name' => 'Faviviajes',
                'id_agencia' => 891,
                'id_de_agente' => 'A343995',
                'rename' => false,
                'match_names' => [
                ],
            ],
            62 => [
                'name' => 'Ferlugi Travel, Viajes y Turismo, C.A.',
                'id_agencia' => 834,
                'id_de_agente' => 'A343938',
                'rename' => false,
                'match_names' => [
                ],
            ],
            63 => [
                'name' => 'FG Travel',
                'id_agencia' => 781,
                'id_de_agente' => 'A343885',
                'rename' => false,
                'match_names' => [
                ],
            ],
            64 => [
                'name' => 'Flying Planner, INC',
                'id_agencia' => 899,
                'id_de_agente' => 'A344003',
                'rename' => false,
                'match_names' => [
                ],
            ],
            65 => [
                'name' => 'Gaviota Tours',
                'id_agencia' => 910,
                'id_de_agente' => 'A344014',
                'rename' => false,
                'match_names' => [
                ],
            ],
            66 => [
                'name' => 'Geneviv Viajes y Turismo CA',
                'id_agencia' => 754,
                'id_de_agente' => 'A343858',
                'rename' => false,
                'match_names' => [
                ],
            ],
            67 => [
                'name' => 'Gestiona Tu Viaje Corp.',
                'id_agencia' => 904,
                'id_de_agente' => 'A344008',
                'rename' => false,
                'match_names' => [
                ],
            ],
            68 => [
                'name' => 'Go To Travel',
                'id_agencia' => 861,
                'id_de_agente' => 'A343965',
                'rename' => false,
                'match_names' => [
                ],
            ],
            69 => [
                'name' => 'Go Travel World',
                'id_agencia' => 961,
                'id_de_agente' => 'A344065',
                'rename' => false,
                'match_names' => [
                ],
            ],
            70 => [
                'name' => 'Goals Travel',
                'id_agencia' => 971,
                'id_de_agente' => 'A344075',
                'rename' => false,
                'match_names' => [
                ],
            ],
            71 => [
                'name' => 'Gold Vip',
                'id_agencia' => 901,
                'id_de_agente' => 'A344005',
                'rename' => false,
                'match_names' => [
                ],
            ],
            72 => [
                'name' => 'Gold Vip Travel Services, C.A.',
                'id_agencia' => 789,
                'id_de_agente' => 'A343893',
                'rename' => false,
                'match_names' => [
                ],
            ],
            73 => [
                'name' => 'Gonzalez Tours',
                'id_agencia' => 964,
                'id_de_agente' => 'A344068',
                'rename' => false,
                'match_names' => [
                ],
            ],
            74 => [
                'name' => 'Hermes Viajes y Turismo',
                'id_agencia' => 877,
                'id_de_agente' => 'A343981',
                'rename' => false,
                'match_names' => [
                ],
            ],
            75 => [
                'name' => 'Hyper Travels, C.A',
                'id_agencia' => 828,
                'id_de_agente' => 'A343932',
                'rename' => false,
                'match_names' => [
                ],
            ],
            76 => [
                'name' => 'Ika bara viajes y turismo',
                'id_agencia' => 777,
                'id_de_agente' => 'A343881',
                'rename' => false,
                'match_names' => [
                ],
            ],
            77 => [
                'name' => 'Inspira Viajes',
                'id_agencia' => 929,
                'id_de_agente' => 'A344033',
                'rename' => false,
                'match_names' => [
                ],
            ],
            78 => [
                'name' => 'Jassuyus Tours, C.A',
                'id_agencia' => 913,
                'id_de_agente' => 'A344017',
                'rename' => false,
                'match_names' => [
                ],
            ],
            79 => [
                'name' => 'Jepira Travel',
                'id_agencia' => 963,
                'id_de_agente' => 'A344067',
                'rename' => false,
                'match_names' => [
                ],
            ],
            80 => [
                'name' => 'Kanko Travel',
                'id_agencia' => 807,
                'id_de_agente' => 'A343911',
                'rename' => false,
                'match_names' => [
                ],
            ],
            81 => [
                'name' => 'Kawy Tours',
                'id_agencia' => 823,
                'id_de_agente' => 'A343927',
                'rename' => false,
                'match_names' => [
                ],
            ],
            82 => [
                'name' => 'KLH Tours',
                'id_agencia' => 858,
                'id_de_agente' => 'A343962',
                'rename' => false,
                'match_names' => [
                ],
            ],
            83 => [
                'name' => 'La Service center',
                'id_agencia' => 776,
                'id_de_agente' => 'A343880',
                'rename' => false,
                'match_names' => [
                ],
            ],
            84 => [
                'name' => 'LAN TRAVEL',
                'id_agencia' => 1005,
                'id_de_agente' => 'A344109',
                'rename' => true,
                'match_names' => [
                    0 => 'LAN TRAVL',
                ],
            ],
            85 => [
                'name' => 'LDL Viajes y Turismo C.A',
                'id_agencia' => 923,
                'id_de_agente' => 'A344027',
                'rename' => false,
                'match_names' => [
                ],
            ],
            86 => [
                'name' => 'Ling Travel C.A',
                'id_agencia' => 738,
                'id_de_agente' => 'A343842',
                'rename' => false,
                'match_names' => [
                ],
            ],
            87 => [
                'name' => 'Los Ram Viajes y Turismo',
                'id_agencia' => 845,
                'id_de_agente' => 'A343949',
                'rename' => false,
                'match_names' => [
                ],
            ],
            88 => [
                'name' => 'Luz del Mar Agencia de Viajes, C.A.',
                'id_agencia' => 893,
                'id_de_agente' => 'A343997',
                'rename' => false,
                'match_names' => [
                ],
            ],
            89 => [
                'name' => 'Mafi Tours, C.A.',
                'id_agencia' => 759,
                'id_de_agente' => 'A343863',
                'rename' => false,
                'match_names' => [
                ],
            ],
            90 => [
                'name' => 'Marilys Viajes y Turismo',
                'id_agencia' => 806,
                'id_de_agente' => 'A343910',
                'rename' => false,
                'match_names' => [
                ],
            ],
            91 => [
                'name' => 'MC AIR Mayorista de Viajes',
                'id_agencia' => 846,
                'id_de_agente' => 'A343950',
                'rename' => false,
                'match_names' => [
                ],
            ],
            92 => [
                'name' => 'MCA Viajes',
                'id_agencia' => 982,
                'id_de_agente' => 'A344086',
                'rename' => false,
                'match_names' => [
                ],
            ],
            93 => [
                'name' => 'Mercy Tours,C.A',
                'id_agencia' => 916,
                'id_de_agente' => 'A344020',
                'rename' => false,
                'match_names' => [
                ],
            ],
            94 => [
                'name' => 'Mil Destinos Agencia de Viajes y Turismo',
                'id_agencia' => 810,
                'id_de_agente' => 'A343914',
                'rename' => false,
                'match_names' => [
                ],
            ],
            95 => [
                'name' => 'Mochila Tours Travel, C.A',
                'id_agencia' => 756,
                'id_de_agente' => 'A343860',
                'rename' => false,
                'match_names' => [
                ],
            ],
            96 => [
                'name' => 'Molina Viajes',
                'id_agencia' => 841,
                'id_de_agente' => 'A343945',
                'rename' => false,
                'match_names' => [
                ],
            ],
            97 => [
                'name' => 'Monumental Tours',
                'id_agencia' => 790,
                'id_de_agente' => 'A343894',
                'rename' => false,
                'match_names' => [
                ],
            ],
            98 => [
                'name' => 'Morrone Tours,C.A',
                'id_agencia' => 801,
                'id_de_agente' => 'A343905',
                'rename' => false,
                'match_names' => [
                ],
            ],
            99 => [
                'name' => 'Mundo Turismo CCS',
                'id_agencia' => 783,
                'id_de_agente' => 'A343887',
                'rename' => false,
                'match_names' => [
                ],
            ],
            100 => [
                'name' => 'Mundo Turismo Santa Fé',
                'id_agencia' => 843,
                'id_de_agente' => 'A343947',
                'rename' => false,
                'match_names' => [
                ],
            ],
            101 => [
                'name' => 'Mundo Zea,C.A',
                'id_agencia' => 820,
                'id_de_agente' => 'A343924',
                'rename' => false,
                'match_names' => [
                ],
            ],
            102 => [
                'name' => 'NAVICU',
                'id_agencia' => 944,
                'id_de_agente' => 'A344048',
                'rename' => false,
                'match_names' => [
                ],
            ],
            103 => [
                'name' => 'NBG Tours',
                'id_agencia' => 980,
                'id_de_agente' => 'A344084',
                'rename' => false,
                'match_names' => [
                ],
            ],
            104 => [
                'name' => 'Nina\'s Travel Worldwide C.A',
                'id_agencia' => 815,
                'id_de_agente' => 'A343919',
                'rename' => true,
                'match_names' => [
                    0 => 'Ninas Travel Worldwide C.A',
                ],
            ],
            105 => [
                'name' => 'Oceans Tours International, C.A',
                'id_agencia' => 761,
                'id_de_agente' => 'A343865',
                'rename' => false,
                'match_names' => [
                ],
            ],
            106 => [
                'name' => 'Ojuani Travels,C.A',
                'id_agencia' => 902,
                'id_de_agente' => 'A344006',
                'rename' => false,
                'match_names' => [
                ],
            ],
            107 => [
                'name' => 'Ola Ola Travel',
                'id_agencia' => 962,
                'id_de_agente' => 'A344066',
                'rename' => false,
                'match_names' => [
                ],
            ],
            108 => [
                'name' => 'On Time Travel Tours, C.A',
                'id_agencia' => 766,
                'id_de_agente' => 'A343870',
                'rename' => false,
                'match_names' => [
                ],
            ],
            109 => [
                'name' => 'Organizacion Kanguro, Mayorista de Turismo',
                'id_agencia' => 693,
                'id_de_agente' => 'A343797',
                'rename' => false,
                'match_names' => [
                ],
            ],
            110 => [
                'name' => 'Over Via del Sol',
                'id_agencia' => 760,
                'id_de_agente' => 'A343864',
                'rename' => false,
                'match_names' => [
                ],
            ],
            111 => [
                'name' => 'Paradise Travel',
                'id_agencia' => 863,
                'id_de_agente' => 'A343967',
                'rename' => false,
                'match_names' => [
                ],
            ],
            112 => [
                'name' => 'PF Travels C.A.',
                'id_agencia' => 768,
                'id_de_agente' => 'A343872',
                'rename' => false,
                'match_names' => [
                ],
            ],
            113 => [
                'name' => 'Piempi Travel Ca',
                'id_agencia' => 724,
                'id_de_agente' => 'A343828',
                'rename' => false,
                'match_names' => [
                ],
            ],
            114 => [
                'name' => 'Prime Travel',
                'id_agencia' => 882,
                'id_de_agente' => 'A343986',
                'rename' => false,
                'match_names' => [
                ],
            ],
            115 => [
                'name' => 'Proyectos y Promociones Turisticas Paseo',
                'id_agencia' => 784,
                'id_de_agente' => 'A343888',
                'rename' => false,
                'match_names' => [
                ],
            ],
            116 => [
                'name' => 'Replay Travel & Events',
                'id_agencia' => 811,
                'id_de_agente' => 'A343915',
                'rename' => false,
                'match_names' => [
                ],
            ],
            117 => [
                'name' => 'Representaciones AG Travel',
                'id_agencia' => 978,
                'id_de_agente' => 'A344082',
                'rename' => false,
                'match_names' => [
                ],
            ],
            118 => [
                'name' => 'Reserva Tu Viaje',
                'id_agencia' => 750,
                'id_de_agente' => 'A343854',
                'rename' => false,
                'match_names' => [
                ],
            ],
            119 => [
                'name' => 'S.A Prat Viajes',
                'id_agencia' => 795,
                'id_de_agente' => 'A343899',
                'rename' => false,
                'match_names' => [
                ],
            ],
            120 => [
                'name' => 'Sada Tours',
                'id_agencia' => 720,
                'id_de_agente' => 'A343824',
                'rename' => false,
                'match_names' => [
                ],
            ],
            121 => [
                'name' => 'San Port Tours, C.A',
                'id_agencia' => 799,
                'id_de_agente' => 'A343903',
                'rename' => false,
                'match_names' => [
                ],
            ],
            122 => [
                'name' => 'Servitour Araguaney 420 C.A',
                'id_agencia' => 779,
                'id_de_agente' => 'A343883',
                'rename' => false,
                'match_names' => [
                ],
            ],
            123 => [
                'name' => 'Sorocaima tours agencia',
                'id_agencia' => 752,
                'id_de_agente' => 'A343856',
                'rename' => false,
                'match_names' => [
                ],
            ],
            124 => [
                'name' => 'Sorocua Agencia de Viajes y Turismo',
                'id_agencia' => 817,
                'id_de_agente' => 'A343921',
                'rename' => false,
                'match_names' => [
                ],
            ],
            125 => [
                'name' => 'Tianbo International Travel S.A',
                'id_agencia' => 965,
                'id_de_agente' => 'A344069',
                'rename' => false,
                'match_names' => [
                ],
            ],
            126 => [
                'name' => 'Tourism Center',
                'id_agencia' => 956,
                'id_de_agente' => 'A344060',
                'rename' => false,
                'match_names' => [
                ],
            ],
            127 => [
                'name' => 'TOURS CONFORT',
                'id_agencia' => 873,
                'id_de_agente' => 'A343977',
                'rename' => false,
                'match_names' => [
                ],
            ],
            128 => [
                'name' => 'Travel Dreams',
                'id_agencia' => 952,
                'id_de_agente' => 'A344056',
                'rename' => false,
                'match_names' => [
                ],
            ],
            129 => [
                'name' => 'Travel Espacios,C.A',
                'id_agencia' => 922,
                'id_de_agente' => 'A344026',
                'rename' => false,
                'match_names' => [
                ],
            ],
            130 => [
                'name' => 'Travel Factory',
                'id_agencia' => 827,
                'id_de_agente' => 'A343931',
                'rename' => false,
                'match_names' => [
                ],
            ],
            131 => [
                'name' => 'Trippin Venezuela',
                'id_agencia' => 771,
                'id_de_agente' => 'A343875',
                'rename' => false,
                'match_names' => [
                ],
            ],
            132 => [
                'name' => 'Tu Viaje en Primera Clase',
                'id_agencia' => 803,
                'id_de_agente' => 'A343907',
                'rename' => false,
                'match_names' => [
                ],
            ],
            133 => [
                'name' => 'Turamar Viajes',
                'id_agencia' => 786,
                'id_de_agente' => 'A343890',
                'rename' => false,
                'match_names' => [
                ],
            ],
            134 => [
                'name' => 'Turismo Agua Grande',
                'id_agencia' => 788,
                'id_de_agente' => 'A343892',
                'rename' => false,
                'match_names' => [
                ],
            ],
            135 => [
                'name' => 'Turismo Atómico 1468,C.A',
                'id_agencia' => 782,
                'id_de_agente' => 'A343886',
                'rename' => false,
                'match_names' => [
                ],
            ],
            136 => [
                'name' => 'Turismo en un Click T.T.W',
                'id_agencia' => 762,
                'id_de_agente' => 'A343866',
                'rename' => false,
                'match_names' => [
                ],
            ],
            137 => [
                'name' => 'Turismo Global',
                'id_agencia' => 970,
                'id_de_agente' => 'A344074',
                'rename' => false,
                'match_names' => [
                ],
            ],
            138 => [
                'name' => 'Twins Travel Luxury,C.A',
                'id_agencia' => 924,
                'id_de_agente' => 'A344028',
                'rename' => false,
                'match_names' => [
                ],
            ],
            139 => [
                'name' => 'Ven Global Travel',
                'id_agencia' => 713,
                'id_de_agente' => 'A343817',
                'rename' => false,
                'match_names' => [
                ],
            ],
            140 => [
                'name' => 'Viajes 360 Globe',
                'id_agencia' => 847,
                'id_de_agente' => 'A343951',
                'rename' => false,
                'match_names' => [
                ],
            ],
            141 => [
                'name' => 'Viajes Altamar',
                'id_agencia' => 695,
                'id_de_agente' => 'A343799',
                'rename' => false,
                'match_names' => [
                ],
            ],
            142 => [
                'name' => 'Viajes Canaima',
                'id_agencia' => 736,
                'id_de_agente' => 'A343840',
                'rename' => false,
                'match_names' => [
                ],
            ],
            143 => [
                'name' => 'Viajes Central Globe',
                'id_agencia' => 852,
                'id_de_agente' => 'A343956',
                'rename' => false,
                'match_names' => [
                ],
            ],
            144 => [
                'name' => 'Viajes Cosmopolitan Turismo',
                'id_agencia' => 848,
                'id_de_agente' => 'A343952',
                'rename' => false,
                'match_names' => [
                ],
            ],
            145 => [
                'name' => 'Viajes Emitours',
                'id_agencia' => 950,
                'id_de_agente' => 'A344054',
                'rename' => false,
                'match_names' => [
                ],
            ],
            146 => [
                'name' => 'Viajes Humboldt',
                'id_agencia' => 787,
                'id_de_agente' => 'A343891',
                'rename' => false,
                'match_names' => [
                ],
            ],
            147 => [
                'name' => 'Viajes Okey, C.A.',
                'id_agencia' => 809,
                'id_de_agente' => 'A343913',
                'rename' => false,
                'match_names' => [
                ],
            ],
            148 => [
                'name' => 'Viajes One2Trip',
                'id_agencia' => 889,
                'id_de_agente' => 'A343993',
                'rename' => false,
                'match_names' => [
                ],
            ],
            149 => [
                'name' => 'Viajes Oneresglobe',
                'id_agencia' => 981,
                'id_de_agente' => 'A344085',
                'rename' => false,
                'match_names' => [
                ],
            ],
            150 => [
                'name' => 'Viajes Pereira',
                'id_agencia' => 957,
                'id_de_agente' => 'A344061',
                'rename' => false,
                'match_names' => [
                ],
            ],
            151 => [
                'name' => 'Viajes Transpacific CA',
                'id_agencia' => 753,
                'id_de_agente' => 'A343857',
                'rename' => false,
                'match_names' => [
                ],
            ],
            152 => [
                'name' => 'Viajes Viam',
                'id_agencia' => 879,
                'id_de_agente' => 'A343983',
                'rename' => false,
                'match_names' => [
                ],
            ],
            153 => [
                'name' => 'Viajes Viramundo',
                'id_agencia' => 935,
                'id_de_agente' => 'A344039',
                'rename' => false,
                'match_names' => [
                ],
            ],
            154 => [
                'name' => 'Viajes y turismo Capricornio ca',
                'id_agencia' => 808,
                'id_de_agente' => 'A343912',
                'rename' => false,
                'match_names' => [
                ],
            ],
            155 => [
                'name' => 'Viajes y Turismo Halcon, C.A.',
                'id_agencia' => 770,
                'id_de_agente' => 'A343874',
                'rename' => false,
                'match_names' => [
                ],
            ],
            156 => [
                'name' => 'Viajes y Turismo Mar Go, C.A',
                'id_agencia' => 888,
                'id_de_agente' => 'A343992',
                'rename' => false,
                'match_names' => [
                ],
            ],
            157 => [
                'name' => 'Viatur C.A',
                'id_agencia' => 936,
                'id_de_agente' => 'A344040',
                'rename' => false,
                'match_names' => [
                ],
            ],
            158 => [
                'name' => 'VIP Travel',
                'id_agencia' => 876,
                'id_de_agente' => 'A343980',
                'rename' => false,
                'match_names' => [
                ],
            ],
            159 => [
                'name' => 'Vive Un Crucero.com',
                'id_agencia' => 773,
                'id_de_agente' => 'A343877',
                'rename' => false,
                'match_names' => [
                ],
            ],
            160 => [
                'name' => 'Viveturismo',
                'id_agencia' => 862,
                'id_de_agente' => 'A343966',
                'rename' => false,
                'match_names' => [
                ],
            ],
            161 => [
                'name' => 'Volairs Agencia de Viajes y Turismo',
                'id_agencia' => 942,
                'id_de_agente' => 'A344046',
                'rename' => false,
                'match_names' => [
                ],
            ],
            162 => [
                'name' => 'Volare Viajes',
                'id_agencia' => 859,
                'id_de_agente' => 'A343963',
                'rename' => false,
                'match_names' => [
                ],
            ],
            163 => [
                'name' => 'XANADU AGENCIA DE VIAJES Y TURISMO',
                'id_agencia' => 979,
                'id_de_agente' => 'A344083',
                'rename' => false,
                'match_names' => [
                ],
            ],
            164 => [
                'name' => 'Yubens Tours',
                'id_agencia' => 728,
                'id_de_agente' => 'A343832',
                'rename' => false,
                'match_names' => [
                ],
            ],
            165 => [
                'name' => 'Zona de Viajes On Line',
                'id_agencia' => 780,
                'id_de_agente' => 'A343884',
                'rename' => false,
                'match_names' => [
                ],
            ],
        ];
    }

    public static function normalizeName(string $name): string
    {
        $name = trim(mb_strtolower($name, 'UTF-8'));
        $name = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $name,
        );

        return preg_replace('/[^a-z0-9]+/u', '', $name) ?? '';
    }
}
