# specs/ — module spec inbox for `npm run module:make`

Drop ONE JSON per simple FLAT CRUD module here. You (or the AI during the SSD
plan) only write the **attributes** — the run generates the rest of the
scaffolding.

```bash
npm run module:make                        # batch: every specs/*.json below
npm run module:make -- specs/tags.spec.json --dry-run   # preview one
```

- Files starting with `_` or `.` are SKIPPED (use `_example.spec.json` as reference).
- Already-generated modules are SKIPPED in batch mode (delete the module dir or
  batch with `--force` to regenerate).
- One failing spec does NOT stop the rest — failures are reported at the end.
- Schema + annotated example: `stubs/module/module.spec.example.json`
  (copied here as `_example.spec.json`).
- Full guide: `GUIDE/module-make.md`.

Simple CRUD only (≤8 fields, no state machines). Complex modules stay manual
via `/backend-new` — the generator prints the STOP warning itself.
