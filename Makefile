test:
	phpunit
server:
	php -S localhost:8080 -t . router.php
build:
	echo "TODO"
document:
	cd doc && mermaid *.mermaid