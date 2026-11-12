-- Initial account password: 123456. Change it immediately after first login.
INSERT INTO users (name, username, password, role, created_at, updated_at)
SELECT
    'Master Administrator',
    'master',
    '$2b$12$rBrdwXpjg55cD1HUhEZ/.ORUmBX8CMN1aoAWADDSOt9C2.x3IpLk2',
    'master',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE username = 'master'
)
ON CONFLICT (username) DO NOTHING;
