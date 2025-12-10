<?php

use App\Models\JobTitle;
use App\Models\Skill;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Job title IDs that were already updated in the previous migration
        $excludedJobTitleIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 42, 47, 50, 53, 55, 56, 59, 72, 84];
        
        // Get all job titles except the excluded ones
        $jobTitles = JobTitle::whereNotIn('id', $excludedJobTitleIds)->get();
        
        // Get all existing skills
        $allExistingSkills = Skill::all();
        $existingSkillsLower = $allExistingSkills->pluck('name')->map(fn($name) => strtolower(trim($name)))->toArray();
        $existingSkillsMap = $allExistingSkills->pluck('name', 'name')->mapWithKeys(function($name) {
            return [strtolower(trim($name)) => $name];
        })->toArray();
        
        // Helper function to check if skill exists
        $skillExists = function($skillName) use ($existingSkillsLower) {
            return in_array(strtolower(trim($skillName)), $existingSkillsLower);
        };
        
        // Helper function to find matching existing skills
        $findMatchingSkills = function($keywords) use ($existingSkillsMap) {
            $matches = [];
            foreach ($existingSkillsMap as $skillLower => $skillName) {
                foreach ($keywords as $keyword) {
                    if (str_contains($skillLower, strtolower($keyword))) {
                        if (!in_array($skillName, $matches)) {
                            $matches[] = $skillName;
                        }
                    }
                }
            }
            return $matches;
        };
        
        // Define skill mappings for each job title category
        $jobTitleSkillMappings = [];
        
        foreach ($jobTitles as $jobTitle) {
            $skillNames = [];
            $name = strtolower($jobTitle->name);
            $nameEn = strtolower($jobTitle->name_en ?? '');
            
            // Salg & Sales
            if (str_contains($name, 'salgs') || str_contains($name, 'account manager') || str_contains($nameEn, 'sales') || str_contains($nameEn, 'account')) {
                // Find existing matching skills
                $salesKeywords = ['crm', 'salesforce', 'salg', 'account', 'kunde', 'forhandling', 'sales'];
                $matchingSkills = $findMatchingSkills($salesKeywords);
                $skillNames = array_merge($skillNames, $matchingSkills);
                
                // Add skills (both existing and new)
                $salesSkills = [
                    'CRM', 'Salesforce', 'HubSpot', 'Pipedrive', 'Salg', 'Account Management', 
                    'Kundehåndtering', 'Forhandling', 'Pipeline Management', 'Lead Generation', 
                    'B2B Sales', 'B2C Sales', 'Customer Relationship Management', 'Sales Strategy', 
                    'Contract Negotiation', 'Sales Forecasting', 'Territory Management'
                ];
                foreach ($salesSkills as $skill) {
                    // Add if it exists or if it doesn't exist (will be created)
                    if (!in_array($skill, $skillNames) && !in_array(strtolower($skill), array_map('strtolower', $skillNames))) {
                        $skillNames[] = $skill;
                    }
                }
            }
            
            // Marketing
            if (str_contains($name, 'marketing') || str_contains($name, 'somet') || str_contains($name, 'e-commerce') || 
                str_contains($name, 'content') || str_contains($name, 'brand') || 
                str_contains($nameEn, 'marketing') || str_contains($nameEn, 'social media') || 
                str_contains($nameEn, 'e-commerce') || str_contains($nameEn, 'content') || str_contains($nameEn, 'brand')) {
                // Find existing matching skills
                $marketingKeywords = ['google analytics', 'facebook', 'instagram', 'linkedin', 'twitter', 'marketing', 'seo', 'sem', 'content', 'email', 'social'];
                $matchingSkills = $findMatchingSkills($marketingKeywords);
                $skillNames = array_merge($skillNames, $matchingSkills);
                
                // Add skills (both existing and new)
                $marketingSkills = [
                    'Google Analytics', 'Facebook Ads', 'Instagram', 'LinkedIn', 'Twitter', 'Marketing', 
                    'SEO', 'SEM', 'Content Marketing', 'Email Marketing', 'Social Media Marketing', 
                    'Google Ads', 'Meta Ads', 'TikTok', 'YouTube', 'Influencer Marketing', 
                    'Marketing Automation', 'HubSpot Marketing', 'Mailchimp', 'Shopify', 'WooCommerce', 
                    'E-commerce', 'Brand Management', 'Market Research', 'Competitive Analysis', 
                    'Marketing Strategy', 'Campaign Management', 'Content Creation', 'Copywriting', 
                    'Graphic Design', 'Adobe Creative Suite', 'Canva', 'Hootsuite', 'Buffer'
                ];
                foreach ($marketingSkills as $skill) {
                    if (!in_array($skill, $skillNames) && !in_array(strtolower($skill), array_map('strtolower', $skillNames))) {
                        $skillNames[] = $skill;
                    }
                }
            }
            
            // Økonomi & Regnskab / Finance & Accounting
            if (str_contains($name, 'bogholder') || str_contains($name, 'controller') || str_contains($name, 'regnskab') || 
                str_contains($name, 'økonomi') || str_contains($name, 'løn') ||
                str_contains($nameEn, 'accountant') || str_contains($nameEn, 'controller') || 
                str_contains($nameEn, 'finance') || str_contains($nameEn, 'financial') || str_contains($nameEn, 'payroll')) {
                // Find existing matching skills
                $financeKeywords = ['excel', 'regnskab', 'bogføring', 'moms', 'løn', 'sap', 'financial', 'budget', 'power bi', 'tableau'];
                $matchingSkills = $findMatchingSkills($financeKeywords);
                $skillNames = array_merge($skillNames, $matchingSkills);
                
                // Add skills (both existing and new)
                $financeSkills = [
                    'Excel', 'Excel Avanceret', 'Regnskab', 'Bogføring', 'Årsrapporter', 'Moms', 'Løn', 
                    'Netsuite', 'SAP', 'Dynamics 365', 'Navision', 'e-conomic', 'Dinero', 'Power BI', 
                    'Tableau', 'Financial Analysis', 'Budgettering', 'Forecasting', 'Financial Planning', 
                    'IFRS', 'GAAP', 'Audit', 'Taxation', 'VAT', 'Payroll', 'Accounts Payable', 
                    'Accounts Receivable', 'General Ledger', 'Financial Reporting', 'Cost Accounting', 
                    'Management Accounting', 'Bookkeeping', 'Invoice Processing', 'Bank Reconciliation'
                ];
                foreach ($financeSkills as $skill) {
                    if (!in_array($skill, $skillNames) && !in_array(strtolower($skill), array_map('strtolower', $skillNames))) {
                        $skillNames[] = $skill;
                    }
                }
            }
            
            // HR
            if (str_contains($name, 'hr') || str_contains($name, 'rekrutter') || 
                str_contains($nameEn, 'hr') || str_contains($nameEn, 'recruit') || str_contains($nameEn, 'human resource')) {
                // Find existing matching skills
                $hrKeywords = ['rekruttering', 'hr', 'talent', 'recruit', 'human resource', 'performance management'];
                $matchingSkills = $findMatchingSkills($hrKeywords);
                $skillNames = array_merge($skillNames, $matchingSkills);
                
                // Add skills (both existing and new)
                $hrSkills = [
                    'Rekruttering', 'HR', 'Talent Acquisition', 'LinkedIn Recruiter', 'Workday', 
                    'BambooHR', 'Personio', 'HRIS', 'Employee Relations', 'Performance Management', 
                    'Compensation & Benefits', 'Organizational Development', 'Training & Development', 
                    'Onboarding', 'Offboarding', 'HR Analytics', 'People Management', 'Talent Management', 
                    'Succession Planning', 'Employee Engagement', 'Labor Law', 'Employment Law', 
                    'Recruitment Marketing', 'Candidate Sourcing', 'Interviewing', 'Background Checks'
                ];
                foreach ($hrSkills as $skill) {
                    if (!in_array($skill, $skillNames) && !in_array(strtolower($skill), array_map('strtolower', $skillNames))) {
                        $skillNames[] = $skill;
                    }
                }
            }
            
            // Administration
            if (str_contains($name, 'administrativ') || str_contains($name, 'assistant') || str_contains($name, 'sekretær') || 
                str_contains($nameEn, 'administrative') || str_contains($nameEn, 'assistant') || 
                str_contains($nameEn, 'secretary') || str_contains($nameEn, 'executive assistant') || str_contains($nameEn, 'pa')) {
                // Find existing matching skills
                $adminKeywords = ['office', 'excel', 'word', 'powerpoint', 'outlook', 'google workspace'];
                $matchingSkills = $findMatchingSkills($adminKeywords);
                $skillNames = array_merge($skillNames, $matchingSkills);
                
                // Add skills (both existing and new)
                $adminSkills = [
                    'Office 365', 'Google Workspace', 'Excel', 'Word', 'PowerPoint', 'Outlook', 
                    'Calendar Management', 'Travel Planning', 'Event Planning', 'Document Management', 
                    'Filing', 'Data Entry', 'Administration', 'Secretarial Skills', 'Meeting Coordination', 
                    'Expense Management', 'Invoice Processing', 'Reception', 'Phone Etiquette', 
                    'Customer Service', 'Time Management', 'Multi-tasking'
                ];
                foreach ($adminSkills as $skill) {
                    if (!in_array($skill, $skillNames) && !in_array(strtolower($skill), array_map('strtolower', $skillNames))) {
                        $skillNames[] = $skill;
                    }
                }
            }
            
            // Ledelse & Projektledelse (non-IT)
            if ((str_contains($name, 'projektleder') || str_contains($name, 'leder') || str_contains($name, 'chef') || 
                 str_contains($name, 'manager') || str_contains($name, 'direktør') || str_contains($name, 'director')) &&
                !str_contains($name, 'it-') && !str_contains($name, 'it ') && 
                !str_contains($nameEn, 'it ') && !str_contains($nameEn, 'it-')) {
                // Find existing matching skills
                $managementKeywords = ['agile', 'scrum', 'projekt', 'jira', 'confluence', 'team', 'performance', 'leadership', 'kanban'];
                $matchingSkills = $findMatchingSkills($managementKeywords);
                $skillNames = array_merge($skillNames, $matchingSkills);
                
                // Add skills (both existing and new)
                $managementSkills = [
                    'Agile', 'Scrum', 'Projektstyring', 'Jira', 'Confluence', 'Team Ledelse', 
                    'Performance Management', 'Kanban', 'Waterfall', 'Lean', 'Stakeholder Management', 
                    'Budgettering', 'Resource Management', 'Change Management', 'Vendor Management', 
                    'Risikostyring', 'Strategic Planning', 'Business Strategy', 'Leadership', 
                    'People Management', 'Coaching', 'Mentoring', 'Decision Making', 'Problem Solving', 
                    'Conflict Resolution', 'Negotiation', 'Communication', 'Presentation Skills', 
                    'Public Speaking', 'Project Planning', 'Timeline Management', 'Scope Management'
                ];
                foreach ($managementSkills as $skill) {
                    if (!in_array($skill, $skillNames) && !in_array(strtolower($skill), array_map('strtolower', $skillNames))) {
                        $skillNames[] = $skill;
                    }
                }
            }
            
            // Finans analytiker / Financial Analyst
            if (str_contains($name, 'finans analytiker') || str_contains($name, 'financial analyst') || 
                str_contains($nameEn, 'financial analyst')) {
                // Find existing matching skills
                $analystKeywords = ['excel', 'dataanalyse', 'financial', 'power bi', 'tableau', 'analysis', 'sql', 'python'];
                $matchingSkills = $findMatchingSkills($analystKeywords);
                $skillNames = array_merge($skillNames, $matchingSkills);
                
                // Add skills (both existing and new)
                $analystSkills = [
                    'Excel', 'Excel Avanceret', 'Dataanalyse', 'Financial Analysis', 'Power BI', 
                    'Tableau', 'Financial Modeling', 'Valuation', 'Investment Analysis', 'Risk Analysis', 
                    'SQL', 'Python', 'R', 'Bloomberg', 'Reuters', 'Capital IQ', 'DCF Modeling', 
                    'Financial Statements', 'Ratio Analysis', 'Cash Flow Analysis'
                ];
                foreach ($analystSkills as $skill) {
                    if (!in_array($skill, $skillNames) && !in_array(strtolower($skill), array_map('strtolower', $skillNames))) {
                        $skillNames[] = $skill;
                    }
                }
            }
            
            // Driftleder / COO / Operations
            if (str_contains($name, 'drift') || str_contains($name, 'coo') || 
                str_contains($nameEn, 'operations') || str_contains($nameEn, 'coo')) {
                // Find existing matching skills
                $operationsKeywords = ['operations', 'process', 'supply chain', 'quality', 'lean', 'six sigma', 'vendor management'];
                $matchingSkills = $findMatchingSkills($operationsKeywords);
                $skillNames = array_merge($skillNames, $matchingSkills);
                
                // Add skills (both existing and new)
                $operationsSkills = [
                    'Operations Management', 'Process Optimization', 'Supply Chain Management', 
                    'Quality Management', 'Lean Manufacturing', 'Six Sigma', 'KPI Management', 
                    'Performance Metrics', 'Vendor Management', 'Logistics', 'Inventory Management', 
                    'Production Planning', 'Workflow Management', 'Continuous Improvement', 
                    'Process Mapping', 'Standard Operating Procedures', 'SOP'
                ];
                foreach ($operationsSkills as $skill) {
                    if (!in_array($skill, $skillNames) && !in_array(strtolower($skill), array_map('strtolower', $skillNames))) {
                        $skillNames[] = $skill;
                    }
                }
            }
            
            // Maskinmester / Marine Engineer / Facility Management
            if (str_contains($name, 'maskinmester') || str_contains($nameEn, 'marine engineer') || 
                str_contains($nameEn, 'facility')) {
                // Find existing matching skills
                $engineerKeywords = ['technical', 'mechanical', 'safety', 'maintenance', 'engineering', 'compliance'];
                $matchingSkills = $findMatchingSkills($engineerKeywords);
                $skillNames = array_merge($skillNames, $matchingSkills);
                
                // Add skills (both existing and new)
                $engineerSkills = [
                    'Technical Maintenance', 'Mechanical Engineering', 'Safety Management', 
                    'Marine Engineering', 'HVAC', 'Electrical Systems', 'Plumbing', 
                    'Building Maintenance', 'Facility Management', 'Compliance', 'Safety Regulations', 
                    'Preventive Maintenance', 'Troubleshooting', 'Technical Documentation', 
                    'Equipment Maintenance', 'Work Order Management', 'Facility Operations'
                ];
                foreach ($engineerSkills as $skill) {
                    if (!in_array($skill, $skillNames) && !in_array(strtolower($skill), array_map('strtolower', $skillNames))) {
                        $skillNames[] = $skill;
                    }
                }
            }
            
            // CEO / CFO / CTO / Director (Executive level)
            if (str_contains($name, 'direktør') || str_contains($name, 'ceo') || str_contains($name, 'cfo') || 
                str_contains($name, 'cto') || str_contains($nameEn, 'director') || str_contains($nameEn, 'ceo') || 
                str_contains($nameEn, 'cfo') || str_contains($nameEn, 'cto')) {
                // Find existing matching skills
                $executiveKeywords = ['strategic', 'business strategy', 'leadership', 'team', 'performance', 'management', 'stakeholder', 'change management'];
                $matchingSkills = $findMatchingSkills($executiveKeywords);
                $skillNames = array_merge($skillNames, $matchingSkills);
                
                // Add skills (both existing and new)
                $executiveSkills = [
                    'Strategic Planning', 'Business Strategy', 'Leadership', 'Team Ledelse', 
                    'Performance Management', 'Board Relations', 'Investor Relations', 'M&A', 
                    'Corporate Governance', 'Risk Management', 'Crisis Management', 'Public Relations', 
                    'Media Relations', 'Stakeholder Management', 'Change Management', 
                    'Organizational Development', 'Business Development', 'Partnership Development', 
                    'Fundraising', 'Capital Raising', 'IPO', 'Due Diligence'
                ];
                foreach ($executiveSkills as $skill) {
                    if (!in_array($skill, $skillNames) && !in_array(strtolower($skill), array_map('strtolower', $skillNames))) {
                        $skillNames[] = $skill;
                    }
                }
            }
            
            // Product Manager (non-IT)
            if (str_contains($name, 'product manager') && !str_contains($name, 'it')) {
                // Find existing matching skills
                $productKeywords = ['product', 'agile', 'scrum', 'jira', 'confluence', 'stakeholder'];
                $matchingSkills = $findMatchingSkills($productKeywords);
                $skillNames = array_merge($skillNames, $matchingSkills);
                
                // Add skills (both existing and new)
                $productSkills = [
                    'Product Management', 'Product Strategy', 'Product Roadmap', 'User Research', 
                    'Market Research', 'Competitive Analysis', 'Feature Prioritization', 'Backlog Management', 
                    'User Stories', 'Agile', 'Scrum', 'Jira', 'Confluence', 'Stakeholder Management', 
                    'Go-to-Market Strategy', 'Product Launch', 'A/B Testing', 'Analytics'
                ];
                foreach ($productSkills as $skill) {
                    if (!in_array($skill, $skillNames) && !in_array(strtolower($skill), array_map('strtolower', $skillNames))) {
                        $skillNames[] = $skill;
                    }
                }
            }
            
            // Store mapping for this job title
            if (!empty($skillNames)) {
                $jobTitleSkillMappings[$jobTitle->id] = array_unique($skillNames);
            }
        }
        
        // Create all unique skills that don't exist yet
        $allSkills = [];
        foreach ($jobTitleSkillMappings as $skills) {
            $allSkills = array_merge($allSkills, $skills);
        }
        $allSkills = array_unique($allSkills);
        
        foreach ($allSkills as $skillName) {
            Skill::firstOrCreate(['name' => $skillName]);
        }
        
        // Associate skills with job titles
        foreach ($jobTitleSkillMappings as $jobTitleId => $skillNames) {
            $jobTitle = JobTitle::find($jobTitleId);
            if ($jobTitle) {
                $skillIds = Skill::whereIn('name', $skillNames)->pluck('id');
                $jobTitle->skills()->syncWithoutDetaching($skillIds);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Job title IDs that were already updated in the previous migration
        $excludedJobTitleIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 42, 47, 50, 53, 55, 56, 59, 72, 84];
        
        // Get all job titles except the excluded ones
        $jobTitles = JobTitle::whereNotIn('id', $excludedJobTitleIds)->get();
        
        // Remove all skill associations for these job titles
        foreach ($jobTitles as $jobTitle) {
            $jobTitle->skills()->detach();
        }
        
        // Note: We don't delete the skills themselves as they might be used by other job titles
    }
};
