# AGILE OUS — Provisional Bylaws Policy Baseline

**Policy source:** `AGILE - OUS CONSTITUTION AND BY-LAWS (draft).docx`, 12-page document uploaded 24 September 2026.  
**Status:** **DRAFT / NOT RATIFIED**. This is the working requirements and testing baseline, **not** an enforceable final rule for adverse decisions.  
**System policy version:** `DRAFT-2026-09-24-ARTICLE-VI-2`.

## Rules directly supported by the provided draft

| Rule | Exact source | Implemented treatment |
|---|---|---|
| Regular BSIT OUS student enrollment regardless of academic units for ordinary membership | Article III §1 | Basic membership is distinct from officer qualifications |
| Draft membership classes: Regular, Associate, Honorary | Article III §2 | Keep original terminology in documentation; system's existing "General Member" is a working mapping to regular membership, not a rename authorized by the draft |
| President and Vice President candidates must be at least 3rd year | Article VI §2 | Advisory candidacy preview flags lower year levels |
| Other Executive Committee candidates must be at least 2nd year | Article VI §2 | Advisory candidacy preview flags lower year levels |
| Officer candidates must be currently enrolled BSIT OUS regular students with full prescribed academic load | Article VI §2 | Preview flags explicitly unmet qualifications; unresolved inputs require human review |
| Officer candidates must have no recorded 5.0/F, W or D during their **entire stay in the institution** | Article VI §2 | Preview flags draft-listed codes; does not redefine historic eligibility as latest-semester-only |
| Officer candidates must have good moral character and no pending university administrative/disciplinary cases | Article VI §2 | Manual verification required; no AI or inferred moral-character score |
| Board includes Executive Officers and Standing Committee Heads | Article IV §1 | Governance context; does not by itself extend election-candidacy grade clause to all appointees |
| Due process for impeachment includes hearing, defense, and qualified Board vote | Article IX §3 | No automatic dismissal; check appropriate process before implementing personnel actions |

## Working system extensions (not express language of the draft)

- **Every semester:** Request verification for Executive Officers, Committee Heads, Deputy Heads, Committee Members and The Source Code staff.
- **Exclude General Members:** They do not undergo routine semestral grade screening in the system.
- **Verified academic concern:** Flag for assigned authorized reviewer, request clarification and document a decision.
- **Membership continuity:** After an independently authorized role review and appropriate due process, someone who no longer holds an organizational position may remain a General Member. The draft itself **does not prescribe an automatic transfer**.

A check *every semester* defines **review frequency**, not a change to Article VI §2's lookback to grades obtained during the institutional stay. The latter wording addresses officer candidacy. Applying it to standing committee appointees and publication staff is a **pending organizational policy decision**.

## Unresolved inconsistencies and policy decisions

1. The title names **AGILE-OUS**, but Article I / Article X name **PUPOU I-tech Society**. Keep AGILE branding as current project identity, and flag inconsistency for amendment rather than silently editing the constitutional text.
2. Article III says **Regular, Associate and Honorary Members**, while existing operations use **General Member**. Keep the mapping provisional; do not erase distinct draft categories.
3. Article V's standing committee names and the duties appendix are not completely aligned with current AGILE committee structure; review naming and offices before automatic qualification rules.
4. Article VI §2 expressly covers **officer election candidates**; the criteria for committee deputies/members and publication staff have not been enacted in that clause.
5. The draft does not explicitly decide **INC, retaken courses, repeated courses, appeal/correction evidence, disability/accommodation exceptions or disciplinary verification sources**. These require human policy decisions.
6. The election timing contains a **[Second Semester / Final Term]** placeholder (Article VI §3); keep a placeholder in scheduling, not an assumed official date.
7. Effectivity requires ratification and formal PUP OUS administration approval (Article XI §3). Current draft must not be presented as officially in effect.

## Current implementation, specifically for the draft

- Migration `010_provisional_draft_bylaws.sql` **seeds policy version as `is_approved=FALSE`** with source metadata and supported grade flags. It will not accidentally become an authoritative approved academic policy.
- `app/DraftBylawsPolicy.php` provides a deterministic **advisory preview**, labeled draft at all times. Outputs are `review_required`, `needs_more_information`, `no_flags`, or `not_applicable`; no output means automatic eligibility, appointment or removal.
- Preview checks officer candidacy minimum year, current enrollment, academic load, draft-listed grades across supplied historical records, completeness of history and pending human-only factors.
- For committee/personnel/publication roles, any academic flags are labeled a **proposed extension**.
- `tests/draft_bylaws_preview.php` uses exclusively synthetic inputs for reproducible checks.
- Existing `Academic::screen()` continues to require an **approved policy** to write academic verification outcomes, while role transitions remain protected by authorization and policy gates. The draft is **usable for planning, development demonstrations and non-consequential dry runs** without changing those safeguards.

## Future activation checklist

A ratified version is recorded as a **new** immutable policy version, separately marked as approved by a designated authority. In a formal change review: compare Article VI clauses, preserve historical audit records, update draft preview rules as necessary, and run regression/security tests before allowing consequential academic decisions.

**Rule of thumb:** We can code and demonstrate the workflow using this draft today. We cannot represent this unratified draft as university-approved law, or automatically disqualify real students on its basis.
