# remote_aux_control
Удалённое управление системой, используя telegram api

## Для получения обновлений бота используется getUpdates вместо webHook

1. composer i


2. php ./bin/console app:init <system_name> <telegram_bot_secret_key> <telegram_chat_id>

#### Если telegram_chat_id содержит дефис(-), его необходимо заменить на нижнее подчёркивание(_)


3. php ./bin/console app:run