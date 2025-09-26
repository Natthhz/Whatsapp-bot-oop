export const BOT_CONFIGS = [
    {
        id: 'bot1',
        name: 'Bot Utama',
        authDir: 'data/auth_info_bot1',
        port: 3001,
        tablePrefix: 'bot1_',
        allowedNumbers: [
            // "142610161246317",
            // "6281268231405",
            // "6285355787629",
            // "62895603570126"
            "*"
        ],
        enabled: true,
        autoReconnect: true,
        maxReconnectAttempts: 5,
        reconnectInterval: 30000
    },
    {
        id: 'bot2',
        name: 'Bot Kedua',
        authDir: 'data/auth_info_bot2',
        port: 3002,
        tablePrefix: 'bot2_',
        allowedNumbers: [
            // "142610161246317",
            // "6281268231405",
            // "6285355787629",
            // "6281268231405"
            "*"
        ],
        enabled: true,
        autoReconnect: true,
        maxReconnectAttempts: 5,
        reconnectInterval: 30000
    },
    {
        id: 'bot3',
        name: 'Bot Ketiga',
        authDir: 'data/auth_info_bot3',
        port: 3003,
        tablePrefix: 'bot3_',
        allowedNumbers: [
            //  "142610161246317",
            // "6281268231405",
            // "6285355787629",
                        "*"

        ],
        enabled: true,
        autoReconnect: true,
        maxReconnectAttempts: 5,
        reconnectInterval: 30000
    },
     {
        id: 'bot4',
        name: 'Bot Keempat',
        authDir: 'data/auth_info_bot4',
        port: 3004,
        tablePrefix: 'bot3_',
        allowedNumbers: [
            //  "142610161246317",
            // "6281268231405",
            // "6285355787629",
                        "*"

        ],
        enabled: true,
        autoReconnect: true,
        maxReconnectAttempts: 5,
        reconnectInterval: 30000
    }, 
    {
        id: 'bot5',
        name: 'Bot Kelima',
        authDir: 'data/auth_info_bot5',
        port: 3005,
        tablePrefix: 'bot3_',
        allowedNumbers: [
            //  "142610161246317",
            // "6281268231405",
            // "6285355787629",
                        "*"

        ],
        enabled: true,
        autoReconnect: true,
        maxReconnectAttempts: 5,
        reconnectInterval: 30000
    },
     {
        id: 'bot6',
        name: 'Bot Keenam',
        authDir: 'data/auth_info_bot6',
        port: 3006,
        tablePrefix: 'bot3_',
        allowedNumbers: [
            //  "142610161246317",
            // "6281268231405",
            // "6285355787629",
                        "*"

        ],
        enabled: true,
        autoReconnect: true,
        maxReconnectAttempts: 5,
        reconnectInterval: 30000
    },
    {
        id: 'bot7',
        name: 'Bot Ketujuh',
        authDir: 'data/auth_info_bot6',
        port: 3007,
        tablePrefix: 'bot3_',
        allowedNumbers: [
            //  "142610161246317",
            // "6281268231405",
            // "6285355787629",
                        "*"

        ],
        enabled: true,
        autoReconnect: true,
        maxReconnectAttempts: 5,
        reconnectInterval: 30000
    }
];

export const getBotConfig = (botId) => {
    return BOT_CONFIGS.find(config => config.id === botId) || BOT_CONFIGS[0];
};

export const getAllBotConfigs = () => {
    return BOT_CONFIGS.filter(config => config.enabled);
};

export const getBotByPort = (port) => {
    return BOT_CONFIGS.find(config => config.port === port);
};