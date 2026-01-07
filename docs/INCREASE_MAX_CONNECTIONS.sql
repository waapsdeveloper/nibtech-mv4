-- Increase MySQL max_connections to prevent "Too many connections" error
-- Run this as MySQL root user

-- Check current value
SHOW VARIABLES LIKE 'max_connections';

-- Set new value (requires restart or SET GLOBAL)
SET GLOBAL max_connections = 500;

-- Verify the change
SHOW VARIABLES LIKE 'max_connections';

-- To make permanent, add to my.cnf/my.ini under [mysqld]:
-- max_connections = 500
-- wait_timeout = 28800
-- interactive_timeout = 28800
