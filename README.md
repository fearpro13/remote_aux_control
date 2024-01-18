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

## Usage
1. Open required chat in telegram
2. Type one of the supported commands

Supported commands:
- stop - stops script execution
- run <command> - runs command in detached mode(nohup), no print will be available
- reboot - restarts a system (may require privileges)
- output - runs command, result will come in response html file
