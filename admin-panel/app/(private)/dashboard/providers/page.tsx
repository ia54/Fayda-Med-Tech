"use client";

import { useState } from "react";
import { useGetProvidersQuery, useCreateProviderMutation, useUpdateProviderMutation, useDeleteProviderMutation } from "@/store/api/apiSlice";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useToast } from "@/hooks/use-toast";
import { ROLES } from "@/lib/roleConstants";
import { useHasAnyRole } from "@/hooks/usePermissions";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorMessage } from "@/components/error-message";

export default function ProvidersPage() {
  const { data, isLoading, error } = useGetProvidersQuery({});
  const [createProvider] = useCreateProviderMutation();
  const [updateProvider] = useUpdateProviderMutation();
  const [deleteProvider] = useDeleteProviderMutation();
  const { toast } = useToast();
  
  const canManage = useHasAnyRole([ROLES.ADMIN, ROLES.FIRM_ADMIN]);

  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [formData, setFormData] = useState({
    name: "",
    npi: "",
    specialty: "",
    tax_id: "",
    phone: "",
    email: "",
    address: "",
    city: "",
    state: "",
    zip_code: "",
    notes: "",
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      if (editingId) {
        await updateProvider({ id: editingId, ...formData }).unwrap();
        toast({ title: "Success", description: "Provider updated successfully" });
      } else {
        await createProvider(formData).unwrap();
        toast({ title: "Success", description: "Provider created successfully" });
      }
      resetForm();
    } catch (error: any) {
      toast({ title: "Error", description: error.message || "Operation failed", variant: "destructive" });
    }
  };

  const resetForm = () => {
    setFormData({
      name: "", npi: "", specialty: "", tax_id: "", phone: "", email: "",
      address: "", city: "", state: "", zip_code: "", notes: "",
    });
    setEditingId(null);
    setShowForm(false);
  };

  const handleEdit = (provider: any) => {
    setFormData({
      name: provider.name || "",
      npi: provider.npi || "",
      specialty: provider.specialty || "",
      tax_id: provider.tax_id || "",
      phone: provider.phone || "",
      email: provider.email || "",
      address: provider.address || "",
      city: provider.city || "",
      state: provider.state || "",
      zip_code: provider.zip_code || "",
      notes: provider.notes || "",
    });
    setEditingId(provider.id);
    setShowForm(true);
  };

  const handleDelete = async (id: number) => {
    if (!confirm("Are you sure you want to delete this provider?")) return;
    try {
      await deleteProvider(id).unwrap();
      toast({ title: "Success", description: "Provider deleted successfully" });
    } catch (error: any) {
      toast({ title: "Error", description: error.message || "Delete failed", variant: "destructive" });
    }
  };

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorMessage message="Failed to load providers" />;

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Providers</h1>
        {canManage && (
          <Button onClick={() => { resetForm(); setShowForm(true); }}>
            Add Provider
          </Button>
        )}
      </div>

      {showForm && canManage && (
        <Card className="mb-6">
          <CardHeader>
            <CardTitle>{editingId ? "Edit Provider" : "New Provider"}</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label>Provider Name *</label>
                  <Input required value={formData.name} onChange={(e) => setFormData({...formData, name: e.target.value})} placeholder="Provider Name" />
                </div>
                <div>
                  <label>NPI (National Provider Identifier)</label>
                  <Input value={formData.npi} onChange={(e) => setFormData({...formData, npi: e.target.value})} placeholder="NPI" />
                </div>
                <div>
                  <label>Specialty</label>
                  <Input value={formData.specialty} onChange={(e) => setFormData({...formData, specialty: e.target.value})} placeholder="Specialty" />
                </div>
                <div>
                  <label>Tax ID</label>
                  <Input value={formData.tax_id} onChange={(e) => setFormData({...formData, tax_id: e.target.value})} placeholder="Tax ID" />
                </div>
                <div>
                  <label>Phone</label>
                  <Input value={formData.phone} onChange={(e) => setFormData({...formData, phone: e.target.value})} placeholder="Phone" />
                </div>
                <div>
                  <label>Email</label>
                  <Input type="email" value={formData.email} onChange={(e) => setFormData({...formData, email: e.target.value})} placeholder="Email" />
                </div>
                <div className="col-span-2">
                  <label>Address</label>
                  <Input value={formData.address} onChange={(e) => setFormData({...formData, address: e.target.value})} placeholder="Address" />
                </div>
              </div>
              <div className="flex gap-2">
                <Button type="submit">{editingId ? "Update" : "Create"}</Button>
                <Button type="button" variant="outline" onClick={resetForm}>Cancel</Button>
              </div>
            </form>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>NPI</TableHead>
                <TableHead>Specialty</TableHead>
                <TableHead>Phone</TableHead>
                <TableHead>Email</TableHead>
                {canManage && <TableHead>Actions</TableHead>}
              </TableRow>
            </TableHeader>
            <TableBody>
              {data?.data?.data?.map((provider: any) => (
                <TableRow key={provider.id}>
                  <TableCell>{provider.name}</TableCell>
                  <TableCell>{provider.npi}</TableCell>
                  <TableCell>{provider.specialty}</TableCell>
                  <TableCell>{provider.phone}</TableCell>
                  <TableCell>{provider.email}</TableCell>
                  {canManage && (
                    <TableCell>
                      <Button variant="outline" size="sm" onClick={() => handleEdit(provider)}>Edit</Button>
                      <Button variant="destructive" size="sm" onClick={() => handleDelete(provider.id)}>Delete</Button>
                    </TableCell>
                  )}
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}
