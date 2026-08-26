#!/usr/bin/env bash
set -euo pipefail

repository_root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)

for relative in .phpunit.cache .phpunit.result.cache .phpstan.cache build coverage; do
    target=${repository_root}/${relative}
    [[ -e ${target} || -L ${target} ]] || continue
    [[ ! -L ${target} ]] || {
        printf 'Refusing symlinked build artifact root: %s\n' "${target}" >&2
        exit 1
    }
    case ${target} in
        "${repository_root}/"*) ;;
        *) printf 'Refusing cleanup outside repository: %s\n' "${target}" >&2; exit 1 ;;
    esac
    find "${target}" -depth -mindepth 1 -delete 2>/dev/null || true
    if [[ -d ${target} ]]; then
        rmdir -- "${target}"
    else
        unlink -- "${target}"
    fi
done

