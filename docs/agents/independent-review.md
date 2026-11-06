# Independent review

This instruction applies to any agent tool that performs the [independent review](../../SDLC.md#independent-review) of a pull request. The implementing agent starts the reviewer as described in the [review flow](../../SDLC.md#review-flow), and its prompt gives the PR number, the task ID or the direct-change note, the base SHA, and the head SHA. The owner may start the same review in a separate session.

## Rules

- Do not edit, create, or delete repository files. Do not commit or push.
- Start no agents. Where a skill runs parallel agents, such as the two axes of `code-review`, run them one after the other.
- Read AGENTS.md and the documents it makes mandatory, then the approved task from the base SHA, as the [code-review adaptation](skills.md#code-review) describes.
- Memory notes of any agent tool are neither requirements nor evidence. Use only the task at the base SHA, repository documents, and what you verify.
- Check red evidence on the main working tree, because the Docker Compose container mounts it. Return to the starting ref with an empty `git status --short`.
- Report custom tooling that an existing tool could replace as a finding, as AGENTS.md requires.
- Write the report in the [SDLC §4 format](../../SDLC.md#review-report), with one recommended action.

## Steps

1. Record the starting ref with `git rev-parse --abbrev-ref HEAD` and `git rev-parse HEAD`, and confirm that `git status --short` is empty. If it is not, return that the review cannot run.
2. Confirm with `gh pr view <PR> --json headRefOid` that the PR head matches the head SHA in the prompt. If it does not, return that the review cannot run.
3. Review the change with the `code-review` skill where your tool has it, otherwise with the same steps, as its [adaptation](skills.md#code-review) describes.
4. Post the report as one comment with `gh pr comment <PR> --body-file -`, passing the body on standard input.
5. Check out the starting ref again and confirm that `git status --short` is empty.
6. Return only the result, the number of `BLOCKER` and `MAJOR` findings, the recommendation, and the comment URL.
