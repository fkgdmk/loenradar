/**
 * Shared types for report creation flow
 */

export interface JobTitle {
    id: number;
    name: string;
    name_en: string;
    skill_ids: number[];
}

export interface Region {
    id: number;
    name: string;
}

export interface AreaOfResponsibility {
    id: number;
    name: string;
}

export interface ResponsibilityLevel {
    id: number;
    name: string;
}

export interface Skill {
    id: number;
    name: string;
}

export interface UploadedDocument {
    name: string;
    size: number;
    mime_type: string | null;
    preview_url: string | null;
    download_url?: string;
    temp_path?: string;
}

export interface ReportData {
    id: number | string;
    job_title_id: number;
    area_of_responsibility_id: number | null;
    experience: number;
    gender: string | null;
    region_id: number;
    responsibility_level_id: number | null;
    team_size: number | null;
    skill_ids: number[];
    step: number;
    document?: UploadedDocument | null;
    is_guest?: boolean;
}

export interface ReportFormProps {
    job_titles: JobTitle[];
    regions: Region[];
    areas_of_responsibility: AreaOfResponsibility[];
    responsibility_levels: ResponsibilityLevel[];
    skills: Skill[];
    leadership_roles: string[];
    report?: ReportData | null;
}

export interface ReportFormData {
    document: File | null;
    job_title_id: number | null;
    area_of_responsibility_id: number | null;
    experience: number | null;
    gender: string | null;
    region_id: number | null;
    responsibility_level_id: number | null;
    team_size: number | null;
    skill_ids: number[];
}

export interface Step1Errors {
    document: string;
    job_title_id: string;
    area_of_responsibility_id: string;
    experience: string;
    region_id: string;
}

export type ReportMode = 'guest' | 'authenticated';

export interface ReportFormEndpoints {
    step1: string;
    step2: string;
    submit: string;
}

