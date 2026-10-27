# Functions shared by the pipelines in bin/. Sourced from the repository
# root, never executed.

# paint <colour code> <text>
#
# The text in the ANSI colour (32 green, 31 red) when stdout is a terminal
# and NO_COLOR is unset or empty; plain otherwise, so an agent's captured
# output carries no escape codes.
paint() {
    if [[ -t 1 && -z "${NO_COLOR:-}" ]]; then
        printf '\033[%sm%s\033[0m' "$1" "$2"
    else
        printf '%s' "$2"
    fi
}

# run_parallel <command>...
#
# Runs every command at once, captures each one's output, and once all have
# finished prints a line per command in the order given, headed by [ok] or
# [fail] with its duration, followed by the log of each failed command.
# CHECK_VERBOSE=1 prints every log, as the passing ones are otherwise left
# out. A last line counts the failures and names the tools. Output is never
# interleaved. Returns non-zero when any command failed.
run_parallel() {
    local log_dir
    log_dir=$(mktemp -d "${TMPDIR:-/tmp}/delolmo-parallel.XXXXXX")

    local -a commands=("$@")
    local -a pids=()
    local index

    for index in "${!commands[@]}"; do
        (
            local started
            started=$(date +%s)
            bash -c "${commands[$index]}" > "$log_dir/$index.log" 2>&1
            local result=$?
            printf '%s' "$(( $(date +%s) - started ))" > "$log_dir/$index.seconds"
            exit "$result"
        ) &
        pids+=("$!")
    done

    local -a failed=()
    local passed seconds tool

    for index in "${!commands[@]}"; do
        passed=1
        wait "${pids[$index]}" || passed=0

        seconds=$(cat "$log_dir/$index.seconds")

        if (( passed )); then
            paint 32 '[ok]'
            printf '   %s (%ss)\n' "${commands[$index]}" "$seconds"

            if [[ "${CHECK_VERBOSE:-}" == 1 ]]; then
                cat "$log_dir/$index.log"
            fi

            continue
        fi

        paint 31 '[fail]'
        printf ' %s (%ss)\n' "${commands[$index]}" "$seconds"
        cat "$log_dir/$index.log"

        # The tool's name: "composer validate --strict" is validate.
        tool=${commands[$index]#composer }
        failed+=("${tool%% *}")
    done

    rm -rf "$log_dir"

    if (( ${#failed[@]} == 0 )); then
        paint 32 "All ${#commands[@]} passed."
        printf '\n'

        return 0
    fi

    local names
    names=$(IFS=,; printf '%s' "${failed[*]}")

    paint 31 "${#failed[@]} of ${#commands[@]} failed: ${names//,/, }"
    printf '\n'

    return 1
}

# run_once <name> <command>...
#
# Runs the command unless the watched paths are in the same state as when
# the command last succeeded. The state is the git tree hash of those paths'
# working-tree content, built in a scratch index, so it changes when and
# only when a watched file changes: untracked files count, ignored files and
# HEAD do not. A docs-only commit, or committing the very tree a successful
# run already validated, finds the stamp current and skips. The stamp is
# written after every success, whether a git hook or a developer ran the
# pipeline, so a pipeline run by hand is the run the hook trusts.
run_once() {
    local name=$1
    shift

    local stamp_file="var/.stamp-${name}"
    local -a watched_paths=(
        src/
        tests/
        bin/
        composer.json
        composer.lock
        composer-dependency-analyser.php
        phpstan.neon.dist
        psalm.xml
        rector.php
        phpunit.xml.dist
        phpcs.xml.dist
        infection.json.dist
    )

    local scratch_index
    scratch_index=$(mktemp -u "${TMPDIR:-/tmp}/delolmo-index.XXXXXX")

    GIT_INDEX_FILE="$scratch_index" git add --all -- "${watched_paths[@]}"
    local fingerprint
    fingerprint=$(GIT_INDEX_FILE="$scratch_index" git write-tree)
    rm -f "$scratch_index"

    if [[ -f "$stamp_file" && "$(cat "$stamp_file")" == "$fingerprint" ]]; then
        paint 32 "No changes since the last successful ${name} run; skipping."
        printf '\n'
        return 0
    fi

    "$@"

    mkdir -p var
    printf '%s' "$fingerprint" > "$stamp_file"
}
