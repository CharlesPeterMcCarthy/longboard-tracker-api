# longboard_api
PHP ReST API used to receive skate session data from an Arduino Device.

- The Arduino Device calls up a Shell Script (found here: https://github.com/CharlesPeterMcCarthy/longboard_shell_script)
- JSON data is received in this script
- The data recevied must have:
  - API Key (Must match stored API Key)
  - Device Credentials (Name & Pass)
  - Speed Logs
  - Total Distance
- Start time & end time is calculated
- Total distance, start time and end time are stored in `skate_sessions` MySQL table and `sessionID` is generated
- Speeds logs stored in `skate_speeds` MySQL table
- Response data is calculated:
  - Session length
  - Average speed
  - Highest speed
- Response is returned to Shell Script (on Arduino)

### To Use
- Change `{{SERVER_NAME}}` to the Server Name
- Change `{{USER_NAME}}` to the MySQL User Name
- Change `{{PASSWORD}}` to the MySQL User Password
- Change `{{DB_NAME}}` to the MySQL Database Name
- Change `{{API_KEY}}` to the required API key
