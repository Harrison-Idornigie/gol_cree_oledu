'use client';

import { Button } from "@/components/ui/button";
import { useToast } from "@/components/ui/toast";
import { createUnit, updateUnit, UnitData } from "@/app/_actions/admin/unit-actions";
import { useRouter } from "next/navigation";
import { useState } from "react";

interface UnitFormProps {
    initialData?: UnitData & { id?: number };
    onSuccess?: () => void;
}

export function UnitForm({ initialData, onSuccess }: UnitFormProps) {
    const router = useRouter();
    const { showToast } = useToast();
    const [loading, setLoading] = useState(false);
    const [formData, setFormData] = useState<UnitData>({
