# src/Modules — módulos del dominio

Cada módulo es una mini-aplicación con un **modo de complejidad declarado** en su propio
`ARCHITECTURE.md`. El modo nunca se infiere del árbol de carpetas: un módulo plano y uno en capas se
ven iguales hasta que sabes cuál es cuál.

**Regla completa, triggers y anti-patrones** → `.opencode/skills/backend-php/SKILL-ARCHITECTURE.md`

## Los dos modos

**FLAT** (por defecto)

```
Modules/{Modulo}/
├── Models/  Http/{Controllers,Requests}/  Actions/  Enums/  Events/
├── Jobs/  Policies/  Exports/  Views/  Routes/{web,api}.php
├── ARCHITECTURE.md
└── Tests/
```

**PROGRESSIVE** (solo con ≥2 triggers cumplidos)

```
Modules/{Modulo}/
├── Domain/          # puro: sin Eloquent, sin Http, sin framework
│   ├── Models/  ValueObjects/  Enums/  Events/  Exceptions/
│   ├── Policies/  Services/  Repositories?/
├── Application/     # casos de uso
│   ├── Actions/  Queries/  Dtos/
├── Infrastructure/
│   ├── Persistence/Eloquent/  Migrations/  Services/
├── Http/  Routes/  ARCHITECTURE.md  Tests/
```

`Domain/` no importa nada de `Infrastructure/`, `Http/` ni `Illuminate\Database`.

## Los 4 triggers (≥2 para PROGRESSIVE)

1. ≥3 modelos con reglas de negocio
2. Invariantes — un estado que no se puede violar
3. Los tests necesitan sustituir persistencia o un externo
4. Está en el roadmap para extraerse a microservicio

## Los dos modos de fallo

| Fallo               | Síntoma                                                                    |
| ------------------- | -------------------------------------------------------------------------- |
| **Sub-declarado**   | Parece plano, tiene invariantes. Alguien "lo simplifica" y rompe una regla |
| **Sobre-declarado** | Le pusieron 4 capas "porque enterprise". Cada CRUD toca 9 ficheros         |

Ambos son **complejidad no declarada**. La mezcla no es el problema; la mezcla invisible sí.

## Al crear un módulo

1. `Modules/{Modulo}/ARCHITECTURE.md` — copia `stubs/architecture/ARCHITECTURE.FLAT.md`
   o `ARCHITECTURE.PROGRESSIVE.md` y rellena modo, triggers cumplidos, deuda asumida
2. Si es PROGRESSIVE, un `Providers/{Modulo}ServiceProvider.php` registrado a mano en
   `bootstrap/providers.php` (Laravel 11+ solo autodescubre `app/Providers/`)
3. `composer dump-autoload`

`tests/Architecture/ModuleComplexityTest.php` falla si un módulo no declara su modo, o si el árbol no
coincide con lo declarado.

## De-escalación

Baja a FLAT cuando el módulo cumpla **0/4 triggers durante dos quarters** y su `Domain/` no tenga
invariantes cubiertos por tests. De-escalar es mover ficheros, no borrar lógica.

## La regla sin cerrojo

> Un `Domain/` sin **ningún test que falle si alguien lo mueve** no es una capa. Es una carpeta.
> Las capas sin un test que las imponga son estructura de comentario.
