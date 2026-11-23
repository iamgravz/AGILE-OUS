<?php

declare(strict_types=1);

// AGILE's interview question bank, copied from the QUESTION_BANK sheet of AGILE_Applicant_Evaluation_Interview_Management
// [applicant type, committee (null = all), position (null = all), code, question, competency].
// The interviewer sees the core questions, then the ones for the applicant's role, committee and position.
return static function (PDO $db): void {
    $bank = [
        ['core', null, null, 'C1', 'Please introduce yourself and briefly tell us why you want to join AGILE OUS.', 'Motivation & Communication'],
        ['core', null, null, 'C2', 'What do you understand about AGILE OUS and its purpose as a student organization?', 'Organization Understanding'],
        ['core', null, null, 'C3', 'What strengths or skills can you contribute to the organization?', 'Contribution'],
        ['core', null, null, 'C4', 'How will you balance your academic responsibilities with your responsibilities in AGILE?', 'Commitment & Reliability'],
        ['core', null, null, 'C5', 'Tell us about a situation where you worked with other people to accomplish a task. What was your role?', 'Teamwork'],
        ['general', null, null, 'G1', 'What does being an active and responsible member of AGILE mean to you?', 'Membership Mindset'],
        ['general', null, null, 'G2', 'How would you participate even if you were not assigned to a committee or leadership position?', 'Initiative'],
        ['general', null, null, 'G3', 'If AGILE organizes an activity that requires volunteers, how would you decide whether and how you can help?', 'Reliability'],
        ['committee_member', null, null, 'CM1', 'Why did you choose this committee, and what do you hope to contribute to it?', 'Role Fit'],
        ['committee_member', null, null, 'CM2', 'Your Committee Head assigns you a task with a deadline, but you realize you may not finish it on time. What would you do?', 'Accountability'],
        ['committee_member', 'Academics and Training', null, 'CM3', 'If the committee were asked to organize an academic workshop for BSIT OUS students, what would you contribute to its planning or implementation?', 'Academic Program Support'],
        ['committee_member', 'Research and Extension', null, 'CM3', 'How would you help gather and verify reliable information or data for an AGILE research or extension activity?', 'Research & Verification'],
        ['committee_member', 'Creatives, Social Media and Communication', null, 'CM3', 'AGILE needs to announce an important event online. How would you help make the information clear, engaging, accurate, and appropriate?', 'Communication & Creativity'],
        ['committee_member', 'Finance and Fundraising', null, 'CM3', 'If you were helping with a fundraising activity, how would you make sure funds and transactions are properly documented?', 'Financial Accountability'],
        ['committee_member', 'Logistics', null, 'CM3', 'An event is about to start and an important resource or material is missing. What would you do?', 'Problem-Solving'],
        ['committee_member', 'Membership and Student Welfare', null, 'CM3', 'A student approaches you with a concern that may contain private or sensitive information. How would you handle it?', 'Confidentiality & Welfare'],
        ['committee_member', 'Events and External Relations', null, 'CM3', 'If you were helping coordinate with another organization for an AGILE event, how would you communicate and represent AGILE professionally?', 'External Relations'],
        ['deputy', null, null, 'D1', 'Your Committee Head becomes unavailable during an important activity. What would you do to keep the committee functioning?', 'Leadership Continuity'],
        ['deputy', null, null, 'D2', 'Two committee members disagree about how an assigned task should be completed. How would you help resolve the situation?', 'Conflict Resolution'],
        ['deputy', 'Academics and Training', null, 'D3', 'What academic or training initiative would you help the Committee Head implement, and how would you measure whether it helped students?', 'Program Planning'],
        ['deputy', 'Research and Extension', null, 'D3', 'How would you help the Committee Head maintain quality and reliability in research or extension activities?', 'Research Leadership'],
        ['deputy', 'Creatives, Social Media and Communication', null, 'D3', 'How would you coordinate creatives and communications when several AGILE announcements have overlapping deadlines?', 'Creative Coordination'],
        ['deputy', 'Finance and Fundraising', null, 'D3', 'How would you assist the Committee Head in keeping fundraising activities transparent and properly documented?', 'Financial Controls'],
        ['deputy', 'Logistics', null, 'D3', 'How would you prepare a backup plan for logistics if equipment, venue, or materials become unavailable at the last minute?', 'Contingency Planning'],
        ['deputy', 'Membership and Student Welfare', null, 'D3', 'How would you support the Committee Head in handling a sensitive student welfare concern while protecting confidentiality and following proper referral procedures?', 'Welfare Leadership'],
        ['deputy', 'Events and External Relations', null, 'D3', 'How would you help coordinate external partners while ensuring commitments made on behalf of AGILE are clear and authorized?', 'Stakeholder Coordination'],
        ['committee_head', null, null, 'H1', 'You have members with different skills, schedules, and levels of participation. How would you assign responsibilities fairly and keep the committee productive?', 'Leadership & Delegation'],
        ['committee_head', null, null, 'H2', 'One member repeatedly misses deadlines. How would you handle the situation before considering escalation?', 'Accountability & Coaching'],
        ['committee_head', 'Academics and Training', null, 'H3', 'What would be your first priority for Academics and Training during your first month, and how would you turn it into a realistic plan?', 'Strategic Planning'],
        ['committee_head', 'Research and Extension', null, 'H3', 'What standards would you establish to ensure research and extension outputs are reliable, ethical, and useful?', 'Research Governance'],
        ['committee_head', 'Creatives, Social Media and Communication', null, 'H3', 'How would you establish a consistent approval and publishing workflow for AGILE\'s creative and social media content?', 'Communications Governance'],
        ['committee_head', 'Finance and Fundraising', null, 'H3', 'What controls would you establish to make fundraising, collections, expenses, and documentation transparent and accountable?', 'Financial Governance'],
        ['committee_head', 'Logistics', null, 'H3', 'How would you organize inventory, resource requests, assignments, and contingency plans for AGILE activities?', 'Operations Management'],
        ['committee_head', 'Membership and Student Welfare', null, 'H3', 'How would you build a process for receiving, documenting, protecting, and appropriately referring student welfare concerns?', 'Welfare Governance'],
        ['committee_head', 'Events and External Relations', null, 'H3', 'How would you manage external partnerships and events so that responsibilities, approvals, and follow-ups are clearly documented?', 'Partnership Management'],
        ['source_code', null, 'Managing Editor', 'SC1', 'How would you maintain accuracy, quality, consistency, and deadlines across different contributors?', 'Editorial Leadership'],
        ['source_code', null, 'Managing Editor', 'SC2', 'What would you do if an important article contains information that has not yet been verified?', 'Editorial Judgment'],
        ['source_code', null, 'Managing Editor', 'SC3', 'How would you handle disagreement between an editor and a writer about major revisions?', 'Conflict Management'],
        ['source_code', null, 'News Editor', 'SC1', 'How would you determine whether information is reliable enough to publish?', 'Verification'],
        ['source_code', null, 'News Editor', 'SC2', 'An important AGILE event just ended and an article is needed quickly. How would you balance speed and accuracy?', 'News Judgment'],
        ['source_code', null, 'News Editor', 'SC3', 'What information should a good news story contain before publication?', 'News Writing'],
        ['source_code', null, 'Feature Editor', 'SC1', 'How would you turn an ordinary student experience into an engaging feature story?', 'Story Development'],
        ['source_code', null, 'Feature Editor', 'SC2', 'How would you guide a writer whose story has good information but lacks structure?', 'Editing'],
        ['source_code', null, 'Feature Editor', 'SC3', 'What makes a feature article different from straightforward news coverage?', 'Feature Knowledge'],
        ['source_code', null, 'Writer', 'SC1', 'How would you approach a topic you know very little about?', 'Research'],
        ['source_code', null, 'Writer', 'SC2', 'What steps would you take before submitting an article for publication?', 'Writing Process'],
        ['source_code', null, 'Writer', 'SC3', 'If an editor asks you to substantially revise your work, how would you respond?', 'Coachability'],
        ['source_code', null, 'Head Cartoonist', 'SC1', 'How would you maintain creative quality while coordinating other cartoonists?', 'Creative Leadership'],
        ['source_code', null, 'Head Cartoonist', 'SC2', 'What would you do if a proposed illustration could be misunderstood or considered inappropriate?', 'Editorial Judgment'],
        ['source_code', null, 'Head Cartoonist', 'SC3', 'How would you distribute illustration assignments fairly while meeting deadlines?', 'Delegation'],
        ['source_code', null, 'Cartoonist', 'SC1', 'How would you translate an article or idea into an effective visual illustration?', 'Visual Interpretation'],
        ['source_code', null, 'Cartoonist', 'SC2', 'What would you do if your artistic interpretation differed from the editor\'s intended message?', 'Collaboration'],
        ['source_code', null, 'Cartoonist', 'SC3', 'How do you work under a creative deadline while maintaining quality?', 'Time Management'],
        ['source_code', null, 'Layout Editor', 'SC1', 'How would you prioritize readability, visual hierarchy, and consistency when laying out a publication?', 'Layout Design'],
        ['source_code', null, 'Layout Editor', 'SC2', 'What would you do if the text and images supplied to you do not fit the available layout?', 'Problem-Solving'],
        ['source_code', null, 'Layout Editor', 'SC3', 'How would you ensure visual consistency across different pages or posts?', 'Design Consistency'],
        ['source_code', null, 'Head Photojournalist', 'SC1', 'How would you assign coverage when several important activities happen at the same time?', 'Coverage Planning'],
        ['source_code', null, 'Head Photojournalist', 'SC2', 'How would you ensure submitted photographs accurately represent an event?', 'Photojournalistic Integrity'],
        ['source_code', null, 'Head Photojournalist', 'SC3', 'How would you manage photographers who have different equipment and skill levels?', 'Team Leadership'],
        ['source_code', null, 'Photojournalist', 'SC1', 'What makes a photograph suitable for documenting a student organization event?', 'Photojournalism'],
        ['source_code', null, 'Photojournalist', 'SC2', 'How would you capture important moments without disrupting an activity?', 'Event Coverage'],
        ['source_code', null, 'Photojournalist', 'SC3', 'What would you consider before using a photograph involving students in a publication?', 'Ethics & Privacy'],
        ['source_code', null, 'Videographer/Editor', 'SC1', 'How would you plan coverage for an AGILE event from shooting through final editing?', 'Production Planning'],
        ['source_code', null, 'Videographer/Editor', 'SC2', 'If you have many clips but a short deadline, how would you decide what belongs in the final video?', 'Editing Judgment'],
        ['source_code', null, 'Videographer/Editor', 'SC3', 'How would you maintain accuracy and context while still making the video engaging?', 'Editorial Integrity'],
    ];

    foreach ($bank as $index => [$type, $committee, $position, $code, $question, $competency]) {
        $exists = $db->prepare('SELECT COUNT(*) FROM interview_questions WHERE applicant_type = ? AND code = ? AND question = ?');
        $exists->execute([$type, $code, $question]);

        if ((int) $exists->fetchColumn() === 0) {
            $db->prepare('INSERT INTO interview_questions (applicant_type, committee, position, code, question, competency, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$type, $committee, $position, $code, $question, $competency, ($index + 1) * 10]);
        }
    }
};
