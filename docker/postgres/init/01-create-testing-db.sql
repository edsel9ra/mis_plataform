SELECT 'CREATE DATABASE mis_project_testing OWNER mis_user'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'mis_project_testing')\gexec
