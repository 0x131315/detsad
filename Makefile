pwd=$(shell pwd)
help: ## list available commands
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'
test:
	for i in {1..3}; do echo "Hello Linux Terminal $i"; done

mysql-8_start: ## mysql-8 start
	docker-compose --project-directory ${pwd} -f ${pwd}/docker-compose.yml up -d mysql-8
mysql-8_stop: ## mysql-8 stop
	docker-compose --project-directory ${pwd} -f ${pwd}/docker-compose.yml stop mysql-8
mysql-8_restart: mysql-8_stop mysql-8_start ## mysql-8 restart

php8.0-fpm_start: ## php8.0-fpm start
	docker-compose --project-directory ${pwd} -f ${pwd}/docker-compose.yml up -d php8.0-fpm
php8.0-fpm_stop: ## php8.0-fpm stop
	docker-compose --project-directory ${pwd} -f ${pwd}/docker-compose.yml stop php8.0-fpm
php8.0-fpm_restart: php8.0-fpm_stop php8.0-fpm_start ## php8.0-fpm restart

nginx_start: ## nginx start
	docker-compose --project-directory ${pwd} -f ${pwd}/docker-compose.yml up -d nginx
nginx_stop: ## nginx stop
	docker-compose --project-directory ${pwd} -f ${pwd}/docker-compose.yml stop nginx
nginx_config_check: ## check nginx config
	./nginx_config_check.sh
nginx_config_reload: ## reload nginx config
	./nginx_config_reload.sh
nginx_restart: nginx_stop nginx_start ## nginx restart

service_start: ## start services
	docker-compose up -d
service_stop: ## stop services
	docker-compose down
service_restart: service_stop service_start ## restart services

composer_install: ## composer install
	./composer install

make_ssl_keys: ## generate ssl keys
	./make_ssl_key

# info
php_info: ## php settings
	./php -i
php_mod: ## php modules
	./php -m
php_mod_enabled: ## php list enabled modules
	./php -i | grep enabled

service_list: ## list services
	docker ps -s
service_stats: ## stat services
	docker stats --no-stream
service_clean: ## clean not used data
	docker system prune -f

symfony_list_commands: ## list all commands
	./php bin/console list
symfony_clear_cache: ## clear cache
	./php bin/console cache:clear
symfony_make_entity: ## new entity
	./php bin/console make:entity
symfony_make_fixtures: ## new fixture
	./php bin/console make:fixtures
symfony_make_migration: ## new migration
	./php bin/console make:migration
symfony_migration_migrate: ## apply migrations
	./php bin/console doctrine:migrations:migrate -n
symfony_migration_revert: ## down last migration
	./php bin/console doctrine:migrations:migrate prev -n
symfony_load_fixtures: ## load fixtures
	./php bin/console doctrine:fixtures:load -n

symfony_custom_analyze: ## analyze data
	./php bin/console custom:analyze

# git
git_list_unmerged_branch:
	@cd ../src && export TERM=xterm && git branch -r --no-merged
