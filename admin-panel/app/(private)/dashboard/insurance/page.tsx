"use client";

import { useState } from "react";
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
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const { currentData: data, isFetching: isLoading, error, refetch } = useGetInsuranceCompaniesQuery({ page, per_page: 10, search });
  const [createInsurance, { isLoading: creating }] = useCreateInsuranceCompanyMutation();
  const [updateInsurance, { isLoading: updating }] = useUpdateInsuranceCompanyMutation();
  const [deleteInsurance, { isLoading: deleting }] = useDeleteInsuranceCompanyMutation();
  const { toast } = useToast();
  
  const canManage = useHasAnyRole([ROLES.ADMIN, ROLES.FIRM_ADMIN]);

  const saving = creating || updating;
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
      setPage(1);
      resetForm();
    } catch (error: any) {
      toast({ title: "Error", description: Object.values(error?.data?.errors || {}).flat().join(" ") || error?.data?.message || "Operation failed", variant: "destructive" });
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
      toast({ title: "Error", description: error?.data?.message || "Delete failed", variant: "destructive" });
    }
  };



  return (
    <div className="p-6">
      <div className="flex flex-wrap gap-3 justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Insurance Companies</h1>
        {canManage && (
          <Button disabled={saving} onClick={() => { resetForm(); setShowForm(true); }}>
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
            <form onSubmit={handleSubmit} className="space-y-4"><fieldset disabled={saving} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label htmlFor="carrier-name">Company Name *</label>
                  <Input required id="carrier-name" value={formData.name} onChange={(e) => setFormData({...formData, name: e.target.value})} placeholder="Company Name" />
                </div>
                <div>
                  <label htmlFor="carrier-phone">Phone</label>
                  <Input id="carrier-phone" value={formData.phone} onChange={(e) => setFormData({...formData, phone: e.target.value})} placeholder="Phone" />
                </div>
                <div>
                  <label htmlFor="carrier-email">Email</label>
                  <Input type="email" id="carrier-email" value={formData.email} onChange={(e) => setFormData({...formData, email: e.target.value})} placeholder="Email" />
                </div>
                <div>
                  <label htmlFor="carrier-payment_rating">Payment Rating (0-5)</label>
                  <Input type="number" min="0" max="5" step="0.01" id="carrier-payment_rating" value={formData.payment_rating} onChange={(e) => setFormData({...formData, payment_rating: e.target.value})} placeholder="Rating" />
                </div>
                <div className="sm:col-span-2">
                  <label htmlFor="carrier-address">Address</label>
                  <Input id="carrier-address" value={formData.address} onChange={(e) => setFormData({...formData, address: e.target.value})} placeholder="Address" />
                </div>
                <div>
                  <label htmlFor="carrier-city">City</label>
                  <Input id="carrier-city" value={formData.city} onChange={(e) => setFormData({...formData, city: e.target.value})} placeholder="City" />
                </div>
                <div>
                  <label htmlFor="carrier-state">State</label>
                  <Input id="carrier-state" value={formData.state} onChange={(e) => setFormData({...formData, state: e.target.value})} placeholder="State" />
                </div>
                <div className="sm:col-span-2">
                  <label htmlFor="carrier-claims_office_address">Claims Office Address</label>
                  <Input id="carrier-claims_office_address" value={formData.claims_office_address} onChange={(e) => setFormData({...formData, claims_office_address: e.target.value})} placeholder="Claims Office Address" />
                </div>
                <div className="sm:col-span-2">
                  <label htmlFor="carrier-notes">Notes</label>
                  <textarea className="w-full p-2 border rounded bg-background" id="carrier-notes" value={formData.notes} onChange={(e) => setFormData({...formData, notes: e.target.value})} placeholder="Notes" />
                </div>
              </div>
              <div className="flex gap-2">
                <Button type="submit">{editingId ? "Update" : "Create"}</Button>
                <Button type="button" variant="outline" onClick={resetForm}>Cancel</Button>
              </div>
            </fieldset></form>
          </CardContent>
        </Card>
      )}

      <Input aria-label="Search carriers" placeholder="Search name or email" value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} className="mb-4" />
      {error ? <div role="alert"><ErrorMessage message="Failed to load insurance companies" /><Button onClick={() => refetch()}>Try again</Button></div> : isLoading ? <LoadingSpinner /> : <Card>
        <CardContent className="p-0">
          {!data?.data?.data?.length && <p className="p-4">No carriers match this search.</p>}
          <div className="md:hidden p-4 space-y-3">{data?.data?.data?.map((company: any) => <article key={company.id} className="border rounded p-3 space-y-2"><h2 className="font-semibold break-words">{company.name}</h2><p className="break-words">{company.email || "No email recorded"}</p><p>{company.phone || "No phone recorded"}</p>{canManage && <div className="flex gap-2"><Button variant="outline" size="sm" disabled={saving} onClick={() => handleEdit(company)}>Edit</Button><Button variant="destructive" size="sm" disabled={deleting} onClick={() => handleDelete(company.id)}>Delete</Button></div>}</article>)}</div>
          <div className="hidden md:block">
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
                      <Button variant="outline" size="sm" disabled={saving} onClick={() => handleEdit(company)}>Edit</Button>
                      <Button variant="destructive" size="sm" disabled={deleting} onClick={() => handleDelete(company.id)}>Delete</Button>
                    </TableCell>
                  )}
                </TableRow>
              ))}
            </TableBody>
          </Table></div>
        </CardContent>
      </Card>}
      <div className="flex justify-between items-center mt-4 gap-2"><Button variant="outline" disabled={page<=1 || isLoading} onClick={() => setPage(p=>p-1)}>Previous</Button><span>Page {page} of {data?.data?.last_page || 1}</span><Button variant="outline" disabled={isLoading || page >= (data?.data?.last_page || 1)} onClick={() => setPage(p=>p+1)}>Next</Button></div>
    </div>
  );
}
