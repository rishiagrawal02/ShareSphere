.PHONY: up down api web test verify clean

up:
	docker compose up -d

down:
	docker compose down

api:
	php -S 127.0.0.1:8000 -t backend/public backend/public/index.php

web:
	cd frontend && npm run dev

test:
	cd backend && vendor/bin/phpunit
	cd frontend && npm run test:run

verify:
	bash scripts/verify.sh

clean:
	docker compose down -v
