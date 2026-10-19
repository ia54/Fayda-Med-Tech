'use client';

import Image from 'next/image';
import { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
  Building2,
  User,
  ShieldCheck,
  Upload,
  Loader2,
  CheckCircle2,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { useGetFirmOrganizationQuery, useUpdateFirmOrganizationMutation } from '@/store/api/firmApiSlice';
import { useToast } from '@/hooks/use-toast';
import { ProtectedRoute } from "@/components/protected-route";
import { ROLES } from "@/lib/roleConstants";
import { Skeleton } from '@/components/ui/skeleton';

export default function FirmSettingsPage() {
  return (
    <ProtectedRoute requiredRole={ROLES.FIRM_ADMIN}>
      <FirmSettingsContent />
    </ProtectedRoute>
  );
}

function FirmSettingsContent() {
  const { toast } = useToast();
  const { data: orgData, isLoading: isOrgLoading, isError, refetch } = useGetFirmOrganizationQuery();
  const [saveError, setSaveError] = useState("");
  const [updateOrg, { isLoading: isUpdating }] = useUpdateFirmOrganizationMutation();

  const [formData, setFormData] = useState({
    org_name: '',
    email: '',
    tax_bin_no: '',
    no_of_employees: '',
    primary_color: '',
    secondary_color: '',
  });

  const [logo, setLogo] = useState<File | null>(null);
  const [logoPreview, setLogoPreview] = useState<string | null>(null);

  useEffect(() => {
    if (orgData?.data) {
      setFormData({
        org_name: orgData.data.org_name || '',
        email: orgData.data.email || '',
        tax_bin_no: orgData.data.tax_bin_no || '',
        no_of_employees: orgData.data.no_of_employees?.toString() || '',
        primary_color: orgData.data.primary_color || '',
        secondary_color: orgData.data.secondary_color || '',
      });
      setLogoPreview(orgData.data.company_logo_url || null);
    }
  }, [orgData]);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const file = e.target.files[0];
      setLogo(file);
      setLogoPreview(URL.createObjectURL(file));
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaveError("");
    try {
      const submitData = new FormData();
      Object.entries(formData).forEach(([key, value]) => {
        submitData.append(key, value);
      });
      if (logo) {
        submitData.append('company_logo', logo);
      }
      submitData.append('_method', 'POST'); // For Laravel multipart updates if using PUT

      await updateOrg(submitData).unwrap();
      toast({
        title: "Success",
        description: "Organization settings updated successfully",
      });
    } catch (error: any) {
      setSaveError(Object.values(error.data?.errors || {}).flat().join(" ") || error.data?.message || "Could not save organization settings.");
      toast({
        title: "Error",
        description: "Failed to update settings",
        variant: "destructive",
      });
    }
  };

  if (isError) return <div role="alert">Could not load organization settings. <Button onClick={() => refetch()}>Try again</Button></div>;

  if (isOrgLoading) {
    return (
      <div className="p-8 space-y-6">
        <Skeleton className="h-10 w-48" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-transparent p-4 md:p-8 space-y-8">
      <div>
        <h1 className="text-3xl font-bold text-emerald-900 dark:text-white">Firm Settings</h1>
        {saveError && <p role="alert" className="text-destructive">{saveError}</p>}
        <p className="text-emerald-700 dark:text-slate-300">
          Manage your organization&apos;s profile and preferences
        </p>
      </div>

      <div className="grid gap-8 lg:grid-cols-3">
        <div className="lg:col-span-1 space-y-4">
          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50">
            <CardHeader>
              <CardTitle className="text-lg flex items-center gap-2">
                <Building2 className="w-5 h-5 text-emerald-600" />
                Firm Profile
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex flex-col items-center gap-4">
                <div className="relative w-32 h-32 rounded-xl border-2 border-dashed border-emerald-200 dark:border-emerald-800 flex items-center justify-center overflow-hidden bg-emerald-50/50 dark:bg-emerald-900/20">
                  {logoPreview ? (
                    <Image unoptimized width={128} height={128} src={logoPreview} alt="Logo" className="w-full h-full object-contain" />
                  ) : (
                    <Building2 className="w-12 h-12 text-emerald-300" />
                  )}
                </div>
                <Label htmlFor="logo-upload" className="cursor-pointer">
                  <div className="flex items-center gap-2 text-sm text-emerald-600 hover:text-emerald-700 font-medium">
                    <Upload className="w-4 h-4" />
                    Change Logo
                  </div>
                  <Input id="logo-upload" type="file" className="hidden" onChange={handleLogoChange} accept=".jpg,.jpeg,.png,.gif" />
                </Label>
              </div>
              <Separator className="bg-emerald-100 dark:bg-emerald-900/50" />
              <div className="space-y-1">
                <p className="text-sm font-medium text-emerald-900 dark:text-white">{orgData?.data?.org_name}</p>
                <p className="text-xs text-emerald-600 dark:text-slate-300">{orgData?.data?.org_type}</p>
                <Badge variant="outline" className="mt-2 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-white border-emerald-200 dark:border-emerald-800">
                  {orgData?.data?.subscription_plan} Plan
                </Badge>
              </div>
            </CardContent>
          </Card>
        </div>

        <div className="lg:col-span-2">
          <form onSubmit={handleSubmit} className="space-y-6">
            <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50">
              <CardHeader>
                <CardTitle className="text-emerald-900 dark:text-white">Organization Details</CardTitle>
                <CardDescription className="dark:text-slate-300">Update your firm&apos;s public information</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="org_name">Organization Name</Label>
                    <Input
                      id="org_name"
                      name="org_name"
                      value={formData.org_name}
                      onChange={handleInputChange}
                      className="bg-white/50 dark:bg-slate-800/50 border-emerald-200 dark:border-emerald-800"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="email">Public Email</Label>
                    <Input
                      id="email"
                      name="email"
                      type="email"
                      value={formData.email}
                      onChange={handleInputChange}
                      className="bg-white/50 dark:bg-slate-800/50 border-emerald-200 dark:border-emerald-800"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="tax_bin_no">Tax/BIN Number</Label>
                    <Input
                      id="tax_bin_no"
                      name="tax_bin_no"
                      value={formData.tax_bin_no}
                      onChange={handleInputChange}
                      className="bg-white/50 dark:bg-slate-800/50 border-emerald-200 dark:border-emerald-800"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="no_of_employees">Number of Employees</Label>
                    <Input
                      id="no_of_employees"
                      name="no_of_employees"
                      type="number"
                      value={formData.no_of_employees}
                      onChange={handleInputChange}
                      className="bg-white/50 dark:bg-slate-800/50 border-emerald-200 dark:border-emerald-800"
                    />
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50">
              <CardHeader>
                <CardTitle className="text-emerald-900 dark:text-white">Branding & Aesthetics</CardTitle>
                <CardDescription className="dark:text-slate-300">Customize your portal colors to match your brand</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div className="space-y-3">
                    <Label htmlFor="primary_color">Primary Brand Color</Label>
                    <div className="flex gap-3">
                      <Input
                        id="primary_color"
                        name="primary_color"
                        type="color"
                        value={formData.primary_color || '#059669'}
                        onChange={handleInputChange}
                        className="w-12 h-10 p-1 bg-white/50 border-emerald-200 cursor-pointer"
                      />
                      <Input
                        name="primary_color"
                        value={formData.primary_color || '#059669'}
                        onChange={handleInputChange}
                        className="flex-1 bg-white/50 border-emerald-200"
                        placeholder="#059669"
                      />
                    </div>
                    <p className="text-[10px] text-muted-foreground">Main accent color for buttons, active states, and highlights.</p>
                  </div>
                  <div className="space-y-3">
                    <Label htmlFor="secondary_color">Secondary Brand Color</Label>
                    <div className="flex gap-3">
                      <Input
                        id="secondary_color"
                        name="secondary_color"
                        type="color"
                        value={formData.secondary_color || '#10b981'}
                        onChange={handleInputChange}
                        className="w-12 h-10 p-1 bg-white/50 border-emerald-200 cursor-pointer"
                      />
                      <Input
                        name="secondary_color"
                        value={formData.secondary_color || '#10b981'}
                        onChange={handleInputChange}
                        className="flex-1 bg-white/50 border-emerald-200"
                        placeholder="#10b981"
                      />
                    </div>
                    <p className="text-[10px] text-muted-foreground">Supporting color for backgrounds, gradients, and secondary UI elements.</p>
                  </div>
                </div>
              </CardContent>
            </Card>

            <div className="flex justify-end gap-4">
              <Button type="button" variant="outline" className="border-emerald-200 text-emerald-700">
                Cancel
              </Button>
              <Button
                type="submit"
                disabled={isUpdating}
                className="bg-emerald-600 hover:bg-emerald-700 text-white min-w-[120px]"
              >
                {isUpdating ? (
                  <>
                    <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                    Saving...
                  </>
                ) : (
                  <>
                    <CheckCircle2 className="w-4 h-4 mr-2" />
                    Save Changes
                  </>
                )}
              </Button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
