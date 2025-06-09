'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import { useToast } from '@/hooks/use-toast';
import { createTenant } from '@/app/_actions/super/tenant-actions';
import { Building2, User, Mail, Globe, FileText, Hash } from 'lucide-react';

interface TenantCreateFormData {
  name: string;
  slug: string;
  domain: string;
  description: string;
  admin_email: string;
  admin_name: string;
}

export default function TenantCreateForm() {
  const router = useRouter();
  const { toast } = useToast();
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState<TenantCreateFormData>({
    name: '',
    slug: '',
    domain: '',
    description: '',
    admin_email: '',
    admin_name: '',
  });
  const [errors, setErrors] = useState<Partial<TenantCreateFormData>>({});

  // Auto-generate slug from name
  const handleNameChange = (value: string) => {
    setFormData(prev => ({
      ...prev,
      name: value,
      slug: value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
    }));
    if (errors.name) {
      setErrors(prev => ({ ...prev, name: undefined }));
    }
  };

  const handleSlugChange = (value: string) => {
    // Only allow lowercase letters, numbers, and hyphens
    const cleanSlug = value.toLowerCase().replace(/[^a-z0-9-]/g, '');
    setFormData(prev => ({ ...prev, slug: cleanSlug }));
    if (errors.slug) {
      setErrors(prev => ({ ...prev, slug: undefined }));
    }
  };

  const handleInputChange = (field: keyof TenantCreateFormData, value: string) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: undefined }));
    }
  };

  const validateForm = (): boolean => {
    const newErrors: Partial<TenantCreateFormData> = {};

    if (!formData.name.trim()) {
      newErrors.name = 'Tenant name is required';
    }

    if (!formData.slug.trim()) {
      newErrors.slug = 'Slug is required';
    } else if (!/^[a-z0-9-]+$/.test(formData.slug)) {
      newErrors.slug = 'Slug can only contain lowercase letters, numbers, and hyphens';
    }

    if (!formData.admin_email.trim()) {
      newErrors.admin_email = 'Admin email is required';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.admin_email)) {
      newErrors.admin_email = 'Please enter a valid email address';
    }

    if (!formData.admin_name.trim()) {
      newErrors.admin_name = 'Admin name is required';
    }

    if (formData.domain && !/^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]*\.?[a-zA-Z]{2,}$/.test(formData.domain)) {
      newErrors.domain = 'Please enter a valid domain name';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) {
      return;
    }

    setLoading(true);

    try {
      const tenantData = {
        name: formData.name.trim(),
        slug: formData.slug.trim(),
        admin_email: formData.admin_email.trim(),
        admin_name: formData.admin_name.trim(),
        ...(formData.domain.trim() && { domain: formData.domain.trim() }),
        ...(formData.description.trim() && { description: formData.description.trim() }),
      };

      const result = await createTenant(tenantData);

      if (result.error) {
        toast({
          title: 'Error',
          description: result.error,
          variant: 'destructive',
        });
        return;
      }

      toast({
        title: 'Success',
        description: 'Tenant created successfully',
      });

      router.push('/super/tenants');
    } catch {
      toast({
        title: 'Error',
        description: 'An unexpected error occurred',
        variant: 'destructive',
      });
    } finally {
      setLoading(false);
    }
  };

  const handleCancel = () => {
    router.push('/super/tenants');
  };

  return (
    <Card className="max-w-2xl mx-auto">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Building2 className="h-5 w-5" />
          Create New Tenant
        </CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Tenant Name */}
          <div className="space-y-2">
            <Label htmlFor="name" className="flex items-center gap-2">
              <Building2 className="h-4 w-4" />
              Tenant Name *
            </Label>
            <Input
              id="name"
              value={formData.name}
              onChange={(e) => handleNameChange(e.target.value)}
              placeholder="e.g., Springfield School District"
              className={errors.name ? 'border-red-500' : ''}
            />
            {errors.name && (
              <p className="text-sm text-red-500">{errors.name}</p>
            )}
          </div>

          {/* Slug */}
          <div className="space-y-2">
            <Label htmlFor="slug" className="flex items-center gap-2">
              <Hash className="h-4 w-4" />
              Slug *
            </Label>
            <Input
              id="slug"
              value={formData.slug}
              onChange={(e) => handleSlugChange(e.target.value)}
              placeholder="e.g., springfield-school-district"
              className={errors.slug ? 'border-red-500' : ''}
            />
            <p className="text-sm text-gray-500">
              Used in URLs and must be unique. Only lowercase letters, numbers, and hyphens allowed.
            </p>
            {errors.slug && (
              <p className="text-sm text-red-500">{errors.slug}</p>
            )}
          </div>

          {/* Domain (Optional) */}
          <div className="space-y-2">
            <Label htmlFor="domain" className="flex items-center gap-2">
              <Globe className="h-4 w-4" />
              Custom Domain
            </Label>
            <Input
              id="domain"
              value={formData.domain}
              onChange={(e) => handleInputChange('domain', e.target.value)}
              placeholder="e.g., learn.springfield.edu"
              className={errors.domain ? 'border-red-500' : ''}
            />
            <p className="text-sm text-gray-500">
              Optional. Custom domain for this tenant&apos;s portal.
            </p>
            {errors.domain && (
              <p className="text-sm text-red-500">{errors.domain}</p>
            )}
          </div>

          {/* Description (Optional) */}
          <div className="space-y-2">
            <Label htmlFor="description" className="flex items-center gap-2">
              <FileText className="h-4 w-4" />
              Description
            </Label>
            <textarea
              id="description"
              className="w-full min-h-[100px] px-3 py-2 rounded-md border border-input bg-background"
              value={formData.description}
              onChange={(e) => handleInputChange('description', e.target.value)}
              placeholder="Brief description of the organization..."
              rows={3}
            />
            <p className="text-sm text-gray-500">
              Optional. Brief description of the tenant organization.
            </p>
          </div>

          {/* Admin Name */}
          <div className="space-y-2">
            <Label htmlFor="admin_name" className="flex items-center gap-2">
              <User className="h-4 w-4" />
              Admin Name *
            </Label>
            <Input
              id="admin_name"
              value={formData.admin_name}
              onChange={(e) => handleInputChange('admin_name', e.target.value)}
              placeholder="e.g., John Smith"
              className={errors.admin_name ? 'border-red-500' : ''}
            />
            {errors.admin_name && (
              <p className="text-sm text-red-500">{errors.admin_name}</p>
            )}
          </div>

          {/* Admin Email */}
          <div className="space-y-2">
            <Label htmlFor="admin_email" className="flex items-center gap-2">
              <Mail className="h-4 w-4" />
              Admin Email *
            </Label>
            <Input
              id="admin_email"
              type="email"
              value={formData.admin_email}
              onChange={(e) => handleInputChange('admin_email', e.target.value)}
              placeholder="e.g., admin@springfield.edu"
              className={errors.admin_email ? 'border-red-500' : ''}
            />
            <p className="text-sm text-gray-500">
              This person will receive admin access to the tenant portal.
            </p>
            {errors.admin_email && (
              <p className="text-sm text-red-500">{errors.admin_email}</p>
            )}
          </div>

          {/* Form Actions */}
          <div className="flex justify-end gap-3 pt-4">
            <Button
              type="button"
              variant="outline"
              onClick={handleCancel}
              disabled={loading}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              disabled={loading}
              className="min-w-[120px]"
            >
              {loading ? 'Creating...' : 'Create Tenant'}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}
