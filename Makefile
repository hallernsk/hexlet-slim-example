start:
	php -S localhost:8080 -t public public/index.php
lint:
	composer run-script phpcs -- --standard=PSR12 public templates