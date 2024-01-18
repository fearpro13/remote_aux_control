# remote_aux_control

## Remote system control from telegram chat

## Setup

### docker-compose:

1. change environment variables(NAME, SECRET, CHAT_ID) in docker-compose.yml
2. `docker compose up`

### native:

1. `composer i`
   > WARNING  
   Rule applies for all arguments!  
   If first symbol is hyphen(-) - it must be replaced with underscore(_)  
   e.g. if telegram_chat_id is -300400500600 then it should be entered as _300400500600
2. `php ./bin/console app:init <system_name> <telegram_bot_secret_key> <telegram_chat_id>`
3. `php ./bin/console app:run`