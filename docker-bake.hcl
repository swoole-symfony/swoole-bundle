variable "PHP_VERSION" {
    default = "8.0"
}

variable "BUILD_TYPE" {
    default = "std"
}

variable "COMPOSER_AUTH" {
    default = ""
}

target "cli" {
    cache-from = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-${BUILD_TYPE}-cli"]
    cache-to   = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-${BUILD_TYPE}-cli,mode=max"]
    output     = ["type=registry"]
}

target "composer" {
    cache-from = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-${BUILD_TYPE}-composer"]
    cache-to   = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-${BUILD_TYPE}-composer,mode=max"]
    output     = ["type=registry"]
}

target "coverage-xdebug" {
    cache-from = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-${BUILD_TYPE}-coverage-xdebug"]
    cache-to   = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-${BUILD_TYPE}-coverage-xdebug,mode=max"]
    output     = ["type=registry"]
}

target "coverage-pcov" {
    cache-from = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-${BUILD_TYPE}-coverage-pcov"]
    cache-to   = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-${BUILD_TYPE}-coverage-pcov,mode=max"]
    output     = ["type=registry"]
}

target "merge-code-coverage" {
    cache-from = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-${BUILD_TYPE}-merge-code-coverage"]
    cache-to   = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-${BUILD_TYPE}-merge-code-coverage,mode=max"]
    output     = ["type=registry"]
}
