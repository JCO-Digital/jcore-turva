.PHONY: all dev ci ci-install install build release watch start stop clean

all: install build

dev: install watch

ci: install build

ci-install: install

install:
	pnpm i
	composer install --no-dev

build:
	pnpm build

release:
	mkdir -p release
	zip release/jcore-turva.zip -r * -x@zip_exclude.txt

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
