<?php

// Design 2 proposed copy: Anatomy of a Document (home section #anatomy, spec §5.4 and §12 C3).
// Read as d2p('anatomy.<key>'); needs client AND legal approval (docs/proposed-copy.md). The texts
// describe what a part of a sample document can show and what Summit checks when reviewing it: no legal
// conclusions, no promises of an outcome. Document names come from proposed-d2/docs.php (docs.name.*).
return [
    'title' => 'Anatomy of a Document',
    'intro' => 'Much of a document review comes down to a few details. Choose a sample document, then one of its parts, to see what that part can show and what we check when we review it.',
    'docs_label' => 'Choose a sample document',
    'parts_label' => 'Parts of the document: :document',
    // Name of the hotspot buttons drawn over the document (one Tab stop; the parts list beside it does the same).
    'hotspots_label' => 'Parts marked on the document: :document',
    'can_show' => 'What it can show',
    'we_check' => 'What we check',
    'caveat' => 'Sample documents for illustration. What a real document shows depends on the whole file and the facts around it.',
    'announce' => ':field. :show',

    'fields' => [
        'cheque' => [
            'date' => [
                'name' => 'Date',
                'show' => 'When the cheque became payable, and whether it was dated in advance.',
                'check' => 'We compare it with the presentation date on the return memo and with the agreement behind the payment.',
            ],
            'payee' => [
                'name' => 'Payee',
                'show' => 'Who the cheque was made out to.',
                'check' => 'We check that the name matches the person or company raising the matter, and any related agreement or invoice.',
            ],
            'words' => [
                'name' => 'Amount in words',
                'show' => 'The amount written out in full by the person who signed.',
                'check' => 'We compare the words with the figures and note any difference for review.',
            ],
            'figures' => [
                'name' => 'Amount in figures',
                'show' => 'The amount written in numbers in the amount box.',
                'check' => 'We check it against the amount in words, the return memo and the invoice or agreement it relates to.',
            ],
            'signature' => [
                'name' => 'Signature',
                'show' => 'Who signed for the account, and whether the account holder is a person or a company.',
                'check' => 'We note who signed and in what capacity, and whether the memo mentions a signature issue.',
            ],
            'memo' => [
                'name' => 'Return memo',
                'show' => 'The reason the bank gave for not paying the cheque, and when it was returned.',
                'check' => 'We read the stated reason and date closely, because different reasons can lead to different next steps.',
            ],
        ],
        'contract' => [
            'parties' => [
                'name' => 'Employer and employee',
                'show' => 'Who the contract names as the employer and as the employee.',
                'check' => 'We compare the names with your other records, such as payslips, letters or your work permit details.',
            ],
            'start' => [
                'name' => 'Start date and term',
                'show' => 'When the work began and for how long the contract was agreed.',
                'check' => 'We use the dates to work out the length of service, which often matters for end-of-service amounts.',
            ],
            'wage' => [
                'name' => 'Basic wage',
                'show' => 'The fixed monthly amount, before any allowances.',
                'check' => 'We look at the basic wage separately from allowances, because they can be treated differently in end-of-service calculations.',
            ],
            'allowances' => [
                'name' => 'Allowances',
                'show' => 'Housing, transport or other amounts paid on top of the basic wage.',
                'check' => 'We list each allowance and compare it with what your payslips show.',
            ],
            'notice' => [
                'name' => 'Notice period',
                'show' => 'How much notice each side agreed to give before ending the contract.',
                'check' => 'We compare the agreed notice with the notice that was actually given or worked.',
            ],
            'signatures' => [
                'name' => 'Signatures',
                'show' => 'That both sides accepted the terms, and when.',
                'check' => 'We check that your copy is signed and dated, and whether a later version exists.',
            ],
        ],
        'tenancy' => [
            'parties' => [
                'name' => 'Landlord and tenant',
                'show' => 'Who lets the property and who rents it.',
                'check' => 'We compare the names with the ownership details and the payment records you have.',
            ],
            'period' => [
                'name' => 'Contract period',
                'show' => 'When the tenancy starts and ends, and so when renewal comes up.',
                'check' => 'We note the end date, because notices about renewal or rent changes are often measured from it.',
            ],
            'rent' => [
                'name' => 'Annual rent',
                'show' => 'The rent agreed for the year and how it is paid.',
                'check' => 'We compare it with the payments made and with any increase you have been asked to pay.',
            ],
            'renewal' => [
                'name' => 'Renewal and notice',
                'show' => 'How either side should give notice about renewal or changes.',
                'check' => 'We compare what the contract says with when and how any notice was actually received.',
            ],
            'registration' => [
                'name' => 'Registration number',
                'show' => 'The reference under which the contract was recorded.',
                'check' => 'We check that the number matches the contract you hold and its current dates.',
            ],
            'signatures' => [
                'name' => 'Signatures',
                'show' => 'That both sides agreed to the terms above.',
                'check' => 'We check that every page and annex you rely on is signed.',
            ],
        ],
        'transfer' => [
            'amount' => [
                'name' => 'Amount and asset',
                'show' => 'How much was sent, and in which asset.',
                'check' => 'We compare the amount with what was agreed and with your wallet or exchange records.',
            ],
            'date' => [
                'name' => 'Date and time',
                'show' => 'When the platform recorded the transfer.',
                'check' => 'We place it on a timeline with the messages and agreements around it.',
            ],
            'wallet' => [
                'name' => 'Recipient wallet',
                'show' => 'The address the asset was sent to, and who the platform shows as the recipient.',
                'check' => 'We note the address exactly, since it links the transfer to its recipient.',
            ],
            'txid' => [
                'name' => 'Transaction ID (TxID)',
                'show' => 'A reference that can be used to look up the transfer on the network.',
                'check' => 'We check that the TxID, the amount and the date agree with each other.',
            ],
            'network' => [
                'name' => 'Network',
                'show' => 'Which blockchain network carried the transfer.',
                'check' => 'We confirm the network, because the same address format can exist on more than one network.',
            ],
            'status' => [
                'name' => 'Status',
                'show' => 'Whether the platform marked the transfer as completed.',
                'check' => 'We compare the status with what actually arrived on the receiving side.',
            ],
        ],
        'notice' => [
            'case' => [
                'name' => 'Case number',
                'show' => 'The reference that identifies the case.',
                'check' => 'We check that the same number appears on every document you receive about the case.',
            ],
            'parties' => [
                'name' => 'Parties',
                'show' => 'Who brought the case and who it is brought against.',
                'check' => 'We check how each party is named, and whether you are named personally or through a company.',
            ],
            'hearing' => [
                'name' => 'Hearing date',
                'show' => 'The date and time set for the hearing.',
                'check' => 'We note it first, because it sets the timetable for any preparation.',
            ],
            'department' => [
                'name' => 'Department',
                'show' => 'Which department the case is listed before.',
                'check' => 'We note it with the case number, so the right details are used in any next step.',
            ],
            'instructions' => [
                'name' => 'What you are asked to do',
                'show' => 'What the notice asks you to do or bring.',
                'check' => 'We list each request and the documents it mentions.',
            ],
            'issued' => [
                'name' => 'Issue date',
                'show' => 'When the notice was issued.',
                'check' => 'We compare it with the date you received it.',
            ],
        ],
    ],
];
