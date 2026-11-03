#!/usr/bin/env bash

# @todo This script is crap. It must fall and properly implemented in Build/Scripts/runTests.sh along with
#       adding two ddev core instances decoupled from the root composer.json.

# Abort on the first failing command. Without this a dependency resolution failure
# went unnoticed: the script still exited 0, and CI only failed later on a missing
# .Build/bin binary.
set -euo pipefail

# Every public TYPO3 12.4 release is affected by security advisories: the fixes after the
# end of regular support are only published as ELTS releases. Composer refuses to install
# such packages, which would make the test setup of this branch impossible. This only
# affects the development installation of this repository, not sites installing the
# extension.
export COMPOSER_NO_SECURITY_BLOCKING=1

composer_cleanup() {
    echo -e "💥 Cleanup folders"
    rm -Rf \
        .cache/phpstan/* \
        .Build/vendor/* \
        .Build/var/* \
        .Build/bin/* \
        .Build/Web/typo3conf/ext/* \
        .Build/Web/typo3/* \
        .Build/Web/typo3temp/* \
        composer.lock
}

# Install the dependency set for one TYPO3 major by temporarily requiring typo3/minimal.
# composer.json is restored afterwards - also if the install fails - so switching the
# core version never leaves the working tree modified.
composer_install_for_core() {
    local constraint="$1"

    cp composer.json composer.json.orig
    trap 'mv composer.json.orig composer.json' EXIT

    echo -e "💪 Enforce TYPO3 ${constraint}"
    php -dxdebug.mode=off "$(which composer)" require --no-update \
        "typo3/minimal":"${constraint}"
    echo -e "🔥 Update to selected dependencies"
    php -dxdebug.mode=off "$(which composer)" install
}

case "${1:-}" in
12)
    composer_cleanup
    composer_install_for_core "^12.4"
    ;;
*)
    echo -e "🌀 Usage: composer-for-core-version.sh 12" >&2
    exit 1
    ;;
esac
