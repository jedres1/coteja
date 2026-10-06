import { useState, useEffect } from 'react';

export function useDepartmentMunicipality(geography, initialMunicipalityId) {
    const [departmentId, setDepartmentId] = useState(() => {
        if (!initialMunicipalityId || !geography?.departments) return '';
        for (const dept of geography.departments) {
            if ((dept.municipalities ?? []).some((m) => m.id === initialMunicipalityId)) {
                return String(dept.id);
            }
        }
        return '';
    });

    const [municipalities, setMunicipalities] = useState(() => {
        if (!initialMunicipalityId || !geography?.departments) return [];
        for (const dept of geography.departments) {
            if ((dept.municipalities ?? []).some((m) => m.id === initialMunicipalityId)) {
                return dept.municipalities ?? [];
            }
        }
        return [];
    });

    useEffect(() => {
        if (!departmentId) {
            setMunicipalities([]);
            return;
        }
        const dept = (geography?.departments ?? []).find((d) => String(d.id) === String(departmentId));
        setMunicipalities(dept?.municipalities ?? []);
    }, [departmentId]);

    return { departmentId, setDepartmentId, municipalities };
}
