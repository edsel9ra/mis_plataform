SELECT format('CREATE DATABASE %I OWNER %I', 'mis_project_testing', current_user)
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'mis_project_testing')\gexec
