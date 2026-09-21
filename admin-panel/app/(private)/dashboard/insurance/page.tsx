"use client";

import { useState, useEffect } from "react";
import { useGetInsuranceCompaniesQuery, useCreateInsuranceCompanyMutation, useUpdateInsuranceCompanyMutation, useDeleteInsuranceCompanyMutation } from "@/store/api/apiSlice";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useToast } from "@/hooks/use-toast";
import { ROLES } from "@/lib/roleConstants";
import { useHasAnyRole } from "@/hooks/usePermissions";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorMessage } from "@/components/error-message";

export default function InsurancePage() {
  const { data, isLoading, error, refetch } = useGetInsuranceCompaniesQuery({});
  const [createInsurance] = useCreateInsuranceCompanyMutation();
  const [updateInsurance] = useUpdateInsuranceCompanyMutation();
  const [deleteInsurance] = useDeleteInsuranceCompanyMutation();
  const { toast } = useToast();
  
  const canManage = useHasAnyRole([ROLES.ADMIN, ROLES.FIRM_ADMIN, ROLES.MEDICAL_BILLER, ROLES.ATTORNEY]);

  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [formData, setFormData] = useState({
    name: "",
    phone: "",
    email: "",
    address: "",
    city: "",
    state: "",
    zip_code: "",
    claims_office_address: "",
    payment_rating: "",
    notes: "",
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      if (editingId) {
        await updateInsurance({ id: editingId, ...formData }).unwrap();
        toast({ title: "Success", description: "Insurance company updated successfully" });
      } else {
        await createInsurance(formData).unwrap();
        toast({ title: "Success", description: "Insurance company created successfully" });
      }
      resetForm();
    } catch (error: any) {
      toast({ title: "Error", description: error.message || "Operation failed", variant: "destructive" });
    }
  };

  const resetForm = () => {
    setFormData({
      name: "", phone: "", email: "", address: "", city: "", state: "", zip_code: "",
      claims_office_address: "", payment_rating: "", notes: "",
    });
    setEditingId(null);
    setShowForm(false);
  };

  const handleEdit = (company: any) => {
    setFormData({
      name: company.name || "",
      phone: company.phone || "",
      email: company.email || "",
      address: company.address || "",
      city: company.city || "",
      state: company.state || "",
      zip_code: company.zip_code || "",
      claims_office_address: company.claims_office_address || "",
      payment_rating: company.payment_rating || "",
      notes: company.notes || "",
    });
    setEditingId(company.id);
    setShowForm(true);
  };

  const handleDelete = async (id: number) => {
    if (!confirm("Are you sure you want to delete this insurance company?")) return;
    try {
      await deleteInsurance(id).unwrap();
      toast({ title: "Success", description: "Insurance company deleted successfully" });
    } catch (error: any) {
      toast({ title: "Error", description: error.message || "Delete failed", variant: "destructive" });
    }
  };

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorMessage message="Failed to load insurance companies" />;

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Insurance Companies</h1>
        {canManage && (
          <Button onClick={() => { resetForm(); setShowForm(true); }}>
            {editingId ? "Editing Insurance" : "Add Insurance Company"}
          </Button>
        )}
      </div>

      {showForm && canManage && (
        <Card className="mb-6">
          <CardHeader>
            <CardTitle>{editingId ? "Edit Insurance Company" : "New Insurance Company"}</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label>Company Name *</label>
                  <Input required value={formData.name} onChange={(e) => setFormData({...formData, name: e.target.value})} placeholder="Company Name" />
                </div>
                <div>
                  <label>Phone</label>
                  <Input value={formData.phone} onChange={(e) => setFormData({...formData, phone: e.target.value})} placeholder="Phone" />
                </div>
                <div>
                  <label>Email</label>
                  <Input type="email" value={formData.email} onChange={(e) => setFormData({...formData, email: e.target.value})} placeholder="Email" />
                </div>
                <div>
                  <label>Payment Rating (0-5)</label>
                  <Input type="number" min="0" max="5" step="0.01" value={formData.payment_rating} onChange={(e) => setFormData({...formData, payment_rating: e.target.value})} placeholder="Rating" />
                </div>
                <div className="col-span-2">
                  <label>Address</label>
                  <Input value={formData.address} onChange={(e) => setFormData({...formData, address: e.target.value})} placeholder="Address" />
                </div>
                <div>
                  <label>City</label>
                  <Input value={formData.city} onChange={(e) => setFormData({...formData, city: e.target.value})} placeholder="City" />
                </div>
                <div>
                  <label>State</label>
                  <Input value={formData.state} onChange={(e) => setFormData({...formData, state: e.target.value})} placeholder="State" />
                </div>
                <div className="col-span-2">
                  <label>Claims Office Address</label>
                  <Input value={formData.claims_office_address} onChange={(e) => setFormData({...formData, claims_office_address: e.target.value})} placeholder="Claims Office Address" />
                </div>
                <div className="col-span-2">
                  <label>Notes</label>
                  <textarea className="w-full p-2 border rounded" value={formData.notes} onChange={(e) => setFormData({...formData, notes: e.target.value})} placeholder="Notes" />
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
                <TableHead>Phone</TableHead>
                <TableHead>Email</TableHead>
                <TableHead>City</TableHead>
                <TableHead>State</TableHead>
                <TableHead>Rating</TableHead>
                {canManage && <TableHead>Actions</TableHead>}
              </TableRow>
            </TableHeader>
            <TableBody>
              {data?.data?.data?.map((company: any) => (
                <TableRow key={company.id}>
                  <TableCell>{company.name}</TableCell>
                  <TableCell>{company.phone}</TableCell>
                  <TableCell>{company.email}</TableCell>
                  <TableCell>{company.city}</TableCell>
                  <TableCell>{company.state}</TableCell>
                  <TableCell>{company.payment_rating}</TableCell>
                  {canManage && (
                    <TableCell>
                      <Button variant="outline" size="sm" onClick={() => handleEdit(company)}>Edit</Button>
                      <Button variant="destructive" size="sm" onClick={() => handleDelete(company.id)}>Delete</Button>
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
