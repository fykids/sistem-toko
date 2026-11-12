$ErrorActionPreference = 'Stop'

php artisan schema:dump --database=sqlite --path=database/schema/sqlite-schema.sql --no-interaction

if ($LASTEXITCODE -ne 0) {
    throw 'Laravel schema export failed.'
}

Write-Output 'Schema exported to database/schema/sqlite-schema.sql.'
Write-Output 'Review the generated SQL before applying it to the remote D1 database.'
