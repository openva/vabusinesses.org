#!/usr/bin/env bash

# Only ask Docker for a TTY when we actually have one. CI has no terminal, and
# "docker exec -it" fails outright there ("cannot attach stdin to a TTY-enabled
# container because stdin is not a terminal").
TTY_FLAGS=()
if [ -t 0 ] && [ -t 1 ]; then
    TTY_FLAGS=(-it)
fi

# Run Bash tests. TEST_SOURCE_DATA is passed through so the caller can opt in to
# the slow tests that re-download the SCC dataset.
docker exec "${TTY_FLAGS[@]}" \
    -e "TEST_SOURCE_DATA=${TEST_SOURCE_DATA:-0}" \
    vabusinesses /var/www/htdocs/deploy/tests/run-all.sh
