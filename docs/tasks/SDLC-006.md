---
id: SDLC-006
status: draft
---

# SDLC-006: Image vulnerability scan with Trivy

## Goal

CI fails when an image that the Compose environment runs has a known critical or high vulnerability in an OS package and a fixed package version exists. Every PR and every push to `main` runs the scan in its own job, next to `quality`, and `make scan-images` runs the same scan locally. Every image in `compose.yaml` uses an exact version tag, so the scan checks the versions the environment runs, and nginx returns to the stable line, which the scanner's data covers.

## Scope

In scope:

- A Make target, `make scan-images`, that scans every image the Compose environment uses.
- A new job in the GitHub Actions CI workflow that runs it.
- Exact version tags for every image in `compose.yaml`.
- A Trivy ignore file for exceptions, created only if the scan needs one.
- [docs/development.md](../development.md#ci-quality-checks), "CI quality checks".

Out of scope:

- A scheduled scan. It belongs to the CD work, which will scan the deployed image.
- Automated pull requests for version updates, such as Dependabot.
- Vulnerabilities in language libraries inside the images, such as the Go standard library in `gosu` or the npm packages bundled with Node.js.
- An audit of the project's JavaScript dependencies.
- Secret and misconfiguration scanning.
- The `Protect main` Ruleset. After merge, the owner adds the new job as a required check. Agents do not change protections (SDLC §5).

## Requirements

### Scan

- Run Trivy 0.76.2 from the official image `aquasec/trivy`, pinned by its digest.
- Scan every image that `docker compose config --images` lists, each once.
- Scan options: the vulnerability scanner only, OS packages only, severities `CRITICAL` and `HIGH`, and unfixed vulnerabilities ignored. A finding fails the scan with a non-zero exit code.
- Pass the locally built PHP image to Trivy as an image archive (`docker save` and `--input`). Do not mount the Docker socket into the Trivy container. Trivy pulls the registry images itself.
- Keep the Trivy cache, including the vulnerability database, in `storage/framework/cache/trivy`. The existing `storage/framework/cache/.gitignore` already ignores it.
- The output names each scanned image and, for each finding, the image, the package, the vulnerability ID, the installed version, and the fixed version.

### CI job

- Add a job to the existing CI workflow that runs on the same events as `quality` and in parallel with it. It builds the PHP image with the same GitHub Actions build cache as `quality`, restores the Trivy cache, and runs `make scan-images`.
- Cache the Trivy cache directory between runs with `actions/cache`. A cache entry cannot be overwritten under its key, so the key contains the current date and `restore-keys` falls back to the newest earlier entry. The first run of a day saves the database it refreshed, and later runs that day reuse it.
- The job takes no secrets.

### Images

- Each image in `compose.yaml` uses an exact version tag: the latest release on its current line when the task is implemented. No major version changes.
- nginx uses the latest stable-line release, at least `1.30.7-alpine`.
- The locally built PHP image keeps its `FROM` line unchanged.

### Exceptions

- An exception lives in `.trivyignore.yaml` and gives the vulnerability ID, an `expired_at` date, and a `statement` with the reason and the image it concerns. Narrow it with `purls` where the finding allows. Each new exception needs the owner's approval in the PR that adds it.
- Trivy reads only `.trivyignore` by default, so when `.trivyignore.yaml` exists, the Make target mounts it into the Trivy container and passes it with `--ignorefile`.
- Today's images pass without exceptions, so the file is created only when a later finding needs one.

### Documentation

In [docs/development.md](../development.md#ci-quality-checks), "CI quality checks":

- The table gains a row for the image scan and its configuration.
- The text says that `make scan-images` runs the same scan as CI, in its own job outside `make ci`.
- The text says that a local scan checks the images on disk, which can be older than the ones CI builds or pulls, even under the same exact tag, and that `docker compose pull` and a rebuild of the PHP image bring them up to date.
- The text says how to add an exception.

## Decisions

- The owner chose detection over bot pull requests on 2026-11-19. Dependabot version updates for Compose were considered and set aside: they open pull requests from a bot, which SDLC does not provide for, and they know nothing about vulnerabilities.
- No scheduled job during the build phase (owner decision, 2026-11-19). Nothing runs outside the owner's machine, and PRs are frequent enough to run the scan. A scheduled scan of the deployed image belongs to CD.
- Trivy over Docker Scout and Grype. Trivy, its database, and its action are Apache-2.0 and need no account or secret. Docker Scout is proprietary, and CI would need a Docker login. Grype was not tested.
- The official Trivy image runs through a Make target instead of `aquasecurity/trivy-action`. The action wraps the same program, versions 0.31.0 to 0.33.1 had a command injection vulnerability, and an unverified report describes a malicious Trivy release in March 2026. The digest pin protects against a moved tag.
- OS packages only (owner decision, 2026-11-19). A spike on 2026-11-19 found fixable language-library findings that only the image maintainers can fix: 1 critical and 26 high in the Go standard library of `gosu` in `postgres:18-alpine`, and 6 high in npm packages bundled with `node:24-alpine`. Scanning them would keep CI red or need lasting exceptions. Our PHP dependencies are covered by `composer audit`.
- nginx moves from mainline `1.31.9` to the stable line (owner decision, 2026-11-19). Trivy matches Alpine images against Alpine's security data, which tracks the stable line. In the spike, `nginx:1.30.6-alpine` showed all five nginx2root CVEs (CVE-2026-123007, CVE-2026-123633, CVE-2026-123634, CVE-2026-121810, CVE-2026-113425), while the vulnerable mainline `nginx:1.31.8-alpine` scanned clean. Alpine's data lists `1.30.7` as fixing all five. The stable line receives only bug and security fixes, and the local configuration uses no feature added in 1.31.
- Exact tags for every image (owner decision, 2026-11-19). CI pulls a floating tag fresh, while the local environment may run an older image under the same tag, so the scan would check something other than what runs. With exact tags, every version change goes through a PR and its scan. The image maintainers still rebuild an exact tag on a newer Alpine base, so the same tag can name a newer image in CI than on disk. A digest pin would prevent that, but every base rebuild would then need a PR, so the task keeps readable tags and documents the gap.
- A separate parallel job, not a step in `bin/ci`. The spike measured about 53 s to download the 1.7 GB database and about 2 minutes to pull and scan the four registry images from a cold cache, against 2 min 54 s for `quality` on PR #33. In parallel, the scan does not lengthen the wait for the result.
- The Make target only loops over `docker compose config --images`, because `trivy image` scans one image per call and no installed tool scans a whole Compose environment.
- The PHP image goes in as an archive, because the socket would give the Trivy container control over the host's Docker. After a fresh build, the PHP image had no fixable critical or high OS findings. An image built on 2026-11-09 had 48.
- A spike on 2026-11-19 with `--pkg-types os` and the other scan options above found no findings in `nginx:1.30.7-alpine`, `postgres:18-alpine`, `rabbitmq:4.2-alpine`, `node:24-alpine`, or a freshly built PHP image, and 9 in `nginx:1.30.6-alpine`.

## Constraints

- The change touches the workflow and `compose.yaml`, so follow [SDLC §6](../../SDLC.md#changes-to-ci-configuration): run `make ci-lint` and `make scan-images`, then check the PR's GitHub Actions run.
- Use the scratchpad or a temporary change for break checks, and do not commit them.

## Risks

- A new vulnerability in a base image turns every PR red until the image is updated or an exception is added. This is intended, as with `composer audit`.
- Detection depends on Alpine's security data. Software that an image builds outside the Alpine package manager, and the nginx mainline line, are not covered.
- The vulnerability database is about 1.7 GB. A cache miss adds about a minute, and the GitHub Actions cache counts it against the repository's cache limit.
- The scan depends on the Trivy database registry being reachable. An outage or a rate limit fails the job.
- Trivy pulls the four registry images from Docker Hub anonymously, on top of the pulls the `quality` job makes, so Docker Hub's limit on anonymous pulls can fail the job.
- An exact tag can still move when the image maintainers rebuild it, so a local image may differ from the one CI scans.
- Exact tags do not update themselves, so fixes arrive only through a PR that a red scan or the owner prompts.

## Acceptance criteria

- [ ] Tool output: `make scan-images` scans every image from `docker compose config --images` once, names each one, and passes on the images this change pins.
- [ ] Break check: with nginx temporarily set to `nginx:1.30.6-alpine`, `make scan-images` fails and reports CVE-2026-123007 for the `nginx` package.
- [ ] Break check: with the PHP image temporarily built from `php:8.5.0-fpm-alpine`, `make scan-images` fails and reports CVE-2026-111497 in `brotli-libs` for the PHP image. A spike on 2026-11-19 built that image and found 30 fixable critical or high OS findings, including that one.
- [ ] Manual check: every image in `compose.yaml` has an exact version tag on its current line, nginx is on the stable line at `1.30.7` or later, the Trivy cache key in the workflow contains the date and has `restore-keys`, and `make ci-lint` passes.
- [ ] CI check: the PR's GitHub Actions run shows the scan job running in parallel with `quality` and passing, and a rerun of the job restores the Trivy cache.
- [ ] Documentation check: development.md states the items in Documentation.

## Dependencies

- Blocked by: none.
