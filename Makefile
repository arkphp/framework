test:
	phpunit
server:
	php -S localhost:8080 -t . router.php
build: zip phar
document:
	cd doc && mermaid *.mermaid
zip:
	mkdir -p tmp
	zip -r tmp/ark_framework.zip . -x ".git/*" "tmp*" "*.DS_Store"
phar:
	mkdir -p tmp
	box build
clean:
	rm -rf tmp