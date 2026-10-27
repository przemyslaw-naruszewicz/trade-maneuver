# Software Development Life Cycle (SDLC)

TODO: Define the project's software development life cycle.

## Changes to CI configuration

For changes to workflows, CI scripts, or their Docker configuration:

1. Run `make ci-lint` (or `sh bin/lint-ci`) to check workflows with actionlint,
   shell scripts with ShellCheck, and Compose configuration.
2. If the change affects pipeline execution, run the affected variant locally:
   `CI_WITH_MUTATION=0 make ci` for PR checks or `make ci` for the full pipeline.
   Run both only when the change affects both variants.
3. After pushing, check the result of the normal GitHub Actions run.

GitHub Actions also runs `make ci-lint` before building the PHP image.
