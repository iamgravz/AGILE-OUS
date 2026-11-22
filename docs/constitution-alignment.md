# System alignment with the AGILE-OUS Constitution and By-Laws

Audit date: November 22, 2026. Source: *Constitution and By-Laws, AGILE-OUS* (16 pages, for ratification in 2026). System: `main` plus the local commits up to `62f7a6d`, and the `agile_ams` database.

Status: **Aligned**, **Partly** (works, but differs in a detail), **Gap** (the by-laws require it and the system does not have it), **Conflict** (the system does something different from the by-laws).

## 1. Summary

| Area | Status | In short |
|---|---|---|
| Name, affiliation | Aligned | AGILE-OUS, PUP Open University System. |
| Contact details | Fixed | `agile.pupous@gmail.com` on the site and in every email; the Facebook link is a web address (still to confirm); the NALLRC address is in the footer and on About. |
| Vision and mission | Fixed | Article II's vision, mission and objectives on About (editable in Settings). |
| Membership eligibility | Aligned | Enrolled BSIT-OUS students; enrollment is confirmed on every application. |
| Membership categories | **Gap** | Only Regular (called General Member). No Associate or Honorary members. |
| Member rights | Partly | Access to resources and records works through accounts; voting is not in the system (see Elections). |
| Fees and dues (₱50) | **Gap** | No fee or receipt records. |
| Data privacy (RA 10173) | Partly | Consent, encryption, access control and audit logs are in place. The end-of-year retention purge is not. |
| Board of Officers | Aligned | Executive Committee (with General Secretary) and committee heads. |
| Standing committees | Aligned | All seven, named as Article V writes them. |
| The Source Code | **Conflict** | In the system as a committee with 11 positions; the by-laws do not mention it. Publications belong to the Creatives committee. |
| Committee Heads and Deputies | Aligned | One head and one deputy per committee. |
| Year-Level Representatives | **Gap** | No position, role or council. |
| Ad Hoc Committees, Advisory Board | **Gap** | No faculty adviser role; no ad hoc committees. |
| Elections (COMELEC, online voting) | **Gap** | Not in the system. |
| Candidate qualifications | Partly | Year level is now recorded; the 3rd-year and 2nd-year minimums are not checked. |
| Term of office, term limits | Partly | Officer roles expire; the two-consecutive-terms limit is not checked. |
| Incompatible offices (4-A) | Partly | One position per person is enforced. Members cannot hold two committee memberships, which the by-laws allow. |
| Who decides, who is elected | Aligned (decided Nov 22) | Committee heads approve or reject applicants; the MSW team sets interview dates; the Executive Board and Year-Level Representatives are elected, never applied for. |
| Transition and handover | Gap (outside the system) | Turnover packets and account handover are manual. |
| Meetings, quorum, notices | Partly | Announcements and email exist; no meeting or quorum records. |
| Financial management | **Gap** | No budget, disbursement or liquidation records. |
| Discipline and impeachment | **Gap** | Not in the system. |
| Official seal on certificates | Aligned | The logo is on the digital ID and certificate. |
| Student welfare (MSW receives concerns confidentially, refers them) | Aligned | Confidential welfare cases with referrals; stricter than the by-laws (only the MSW Head and President read them). |
| Membership applications by MSW | Aligned | MSW manages applications; committee heads decide their own committee's (BR-20). |

## 2. Findings in detail

### Conflicts: the system says something different

**C1. Official email (Article I, letterhead).** The by-laws and letterhead give `agile.pupous@gmail.com`. The footer shows `agile.ous@gmail.com` (`ORG_CONTACT_EMAIL` in `.env`), and Settings › Contact email holds `msw.agileous@gmail.com`. *Fix:* set `ORG_CONTACT_EMAIL=agile.pupous@gmail.com`, keeping the MSW address as the MSW contact. Needs your confirmation that `agile.ous@gmail.com` was a typo.

**C2. Facebook link.** Settings › Facebook page holds `https://AGILE-OUS@facebook.com`, which is not a web address. The footer uses `https://www.facebook.com/agile.ous`, which was never confirmed. *Fix:* the real page address of "AGILE OUS".

**C3. Address (Article I, Section 3).** NALLRC, PUP Main Campus, Anonas St., Sta. Mesa, Manila. Not shown anywhere. *Fix:* add it to the footer and the About page.

**C4. Vision and mission (Article II).** Settings hold sample text. *Fix:* copy Article II Sections 1 to 3 into Settings › Vision, Mission (and the objectives onto About).

**C5. The Source Code.** The system treats it as a committee with 11 positions, decided by the MSW Committee Head, and it is open for application. The by-laws list seven standing committees and give newsletters and publications to the Creatives, Social Media & Communication Committee. *Decision needed:* keep The Source Code as a publication staff outside the by-laws, or move its positions under Creatives.

**C6. Who fills a vacancy (Article VI, Section 5).** The by-laws: a permanent vacancy in the Presidency goes to the Vice President; in the General Secretary's office to the Assistant Secretary; any other vacancy by appointment by the President with a 2/3 Board vote. The system: appointments are prepared by MSW staff and approved by the MSW Committee Head (Step 14), and the MSW Head approves Committee Member and Committee Head applications. *Decision needed:* whether the system should follow the by-laws (President appoints, Board confirms) or the current MSW-led process stays as the operating procedure.

### Partly aligned: small differences

**P1. Names.** "Secretary" should be **General Secretary** (Article IV). "Research and Extensions" is **Research & Extension**; "Finance and Fund Raising" is **Finance & Fundraising**. The signature page calls the Academics head "Academic Support Head", while Article V says Academics & Training; the system follows Article V.

**P2. Candidate qualifications (Article VI, Section 2).** President and Vice President at least 3rd year; other Executive Committee positions at least 2nd year; full load; no 5.0, W or D. The apply page now records the year level, but nothing checks these minimums, and the officer eligibility screening (BR-09) checks failed subjects and enrollment, not W, D or full load.

**P3. Term limits (Article VI, Section 4).** One academic year, re-election to the same post at most two consecutive terms. Roles expire; the term limit is not checked.

**P4. Dual capacity (Article VI, Section 4-A).** The system allows one position per person, which keeps Executive Committee members and heads to one office. General members may join up to two standing committees under the by-laws; the system allows one.

**P5. Data retention (Article III, Section 6).** "Securely archived or safely purged upon the conclusion of each academic year." Still open as L-05 in `security-review.md`: nothing archives or purges at year end.

**P6. Requirements table.** Grades for "Executive Committee officers" and "Year-Level Representatives" apply only through vacancies, because neither is open for application on the apply page.

### Gaps: required by the by-laws, not in the system

| # | By-laws | What would be needed |
|---|---|---|
| G1 | Associate and Honorary members (Art. III §2) | Membership categories; Honorary conferred by the Board |
| G2 | ₱50 voluntary semestral fee, receipts (Art. III §5, Art. IV §6) | Fee records and official receipts |
| G3 | Year-Level Council (Art. V §3) | A Year-Level Representative position per year level |
| G4 | Faculty Adviser and Co-Adviser, Advisory Board (Art. V §5) | An adviser role (read-only oversight, sign-offs) |
| G5 | Ad Hoc Committees (Art. V §4) | Temporary committees that dissolve on report |
| G6 | COMELEC and online elections (Art. VI §1, §3) | Candidacy, verification, secret one-vote-per-member ballot, canvass, proclamation |
| G7 | Financial management (Art. VIII) | Budget, dual authorization, liquidation within 15 days, Auditor verification |
| G8 | Impeachment (Art. IX) | Charges, tribunal, 2/3 Board vote |
| G9 | Meetings and quorum (Art. VII) | Notices 14 days ahead, attendance, quorum |

G6 to G9 are whole modules. They are listed for completeness; the by-laws do not say they must be run through this system.

## 3. Fixed on November 22, 2026

Migration `2026_11_22_constitution_alignment.php` (applied to `agile_ams` after a backup), with the seeds, `.env` and views:

- **C1** Official email `agile.pupous@gmail.com`: `ORG_CONTACT_EMAIL` and Settings, Contact email (every email's footer). The MSW address stays as the MSW contact in the footer.
- **C2** Settings, Facebook page is now `https://www.facebook.com/agile.ous`, the same as the footer. *Still to confirm:* that this is the page "AGILE OUS".
- **C3** Address: in full on About (Quick facts), short in the footer (`ORG_ADDRESS` in `.env`).
- **C4** Vision, mission (as a list) and objectives (new setting `org.objectives`) from Article II, on About. Edited wording is never overwritten.
- **P1** "General Secretary"; "Research and Extension"; "Finance and Fundraising".

### Decisions on November 22, 2026 (C6)

- **Committee Heads approve or reject applicants** for their committees (BR-20, as built). A Committee Head position and The Source Code still go to the MSW Committee Head.
- **The MSW team sets the interview dates** (`interview.manage`, as built).
- **The Executive Board and the Year-Level Representatives are chosen by election** (Article VI), not by application. The system already cannot open them: an HR request must be for a position in the requester's own committee, Executive positions have no committee, and Open for application covers Committee Head to The Source Code only. The apply page now says so.
- Vacancy succession (Article VI Section 5) and the elections themselves stay outside the system until G6 (COMELEC and online voting) is built; Year-Level Representatives (G3) belong with it.

## 4. Recommended order

1. Done: C1 to C4 and P1 (see section 3).
2. **Decision for the Board:** C5 (The Source Code). C6 is decided (section 3).
3. **Next features, if wanted:** G3 Year-Level Representatives, G4 Faculty Adviser, P2 candidate checks, P3 term limits, P5 retention, G1 member categories.
4. **Separate projects:** G6 elections, G7 finance, G2 fees, G8 to G9.
