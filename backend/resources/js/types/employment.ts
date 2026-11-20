export type TeachingAssignment = {
    id: number;
    subject_id: number;
    subject_name: string;
    academic_year: number;
    weekly_hours: string;
};

export type TeacherEmployment = {
    position_title: string;
    status: 'active' | 'on_leave';
    assignments: TeachingAssignment[];
    totals: Pick<TeachingAssignment, 'academic_year' | 'weekly_hours'>[];
};
