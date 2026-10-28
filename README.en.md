# Harpia Radar

[Leia em português](README.md)

The health of the open source projects Brazil depends on, updated daily.

For each listed project, the Radar shows whether it is changing (**Activity**) and how many people sustain those changes (**Sustainability**). The goal is to point out where the community can help: projects that are active but carried by a single person. It was born in [discussion #10](https://github.com/orgs/HarpyBR/discussions/10) of the Harpia community and automates the survey that was first done by hand there.

- **Page (Portuguese):** https://iewerthondev.github.io/harpia-radar/
- **Mirror:** https://iewerthondev.codeberg.page/harpia-radar/
- **Open data:** [`data/snapshot.json`](data/snapshot.json), with history in git

The page is in Portuguese because its audience is Brazilian developers, small businesses and public managers. Code, commits and data keys are in English.

## What it is, and what it is not

The Radar exists to help maintainers, never to expose them. A project carried by one person shows up as "Precisa de mãos" (needs hands), never as "risky" or "dead".

- **Not a ranking.** There is no single score and no "best project".
- **Does not assess** code quality or security.
- **Does not discover projects.** The list is curated by people through pull requests.
- **Shows no names.** The page only has aggregate numbers.
- **Has no** server, database, login, cookies, analytics or AI.

## Badges

| Badge | Values |
| --- | --- |
| Activity | active · active_no_recent_release · no_recent_changes · archived |
| Sustainability | well_distributed · few_people · needs_hands · insufficient_data |

Thresholds live in [`config/health.yaml`](config/health.yaml) and can be discussed in pull requests. The full specification (in Portuguese) is in [`docs/SPEC.md`](docs/SPEC.md).

## Running locally

Only Docker is required.

```sh
docker compose build php
docker compose run --rm php composer install
export GITHUB_TOKEN=your_token   # read-only access to public repositories
docker compose run --rm php bin/radar validate
docker compose run --rm php bin/radar collect
docker compose run --rm php bin/radar build
docker compose run --rm php composer test
docker compose run --rm php composer stan
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) (in Portuguese). Pull requests in English or Portuguese are welcome.

## License

Code under the [MIT](LICENSE) license. Data in `data/` under [CC BY 4.0](data/LICENSE).
