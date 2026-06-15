.PHONY: all dev ci ci-install install build i18n release watch start stop clean

all: install build i18n

dev: install watch

ci: install build i18n

ci-install: install

install:
	pnpm i
	composer install --no-dev

build:
	pnpm build

i18n:
	pnpm run make-pot
	msgfmt -o languages/jcore-turva-sv_SE.mo languages/jcore-turva-sv_SE.po
	msgfmt -o languages/jcore-turva-fi.mo languages/jcore-turva-fi.po
	wp i18n make-json languages/ --no-purge

release:
	mkdir -p release
	zip release/jcore-turva.zip -r * -x@.zipexclude

watch:
	pnpm run watch

start:
	pnpm run env:start

stop:
	pnpm run env:stop

clean:
	rm -rf node_modules
	rm -rf vendor
	rm -rf build
	rm -rf release
