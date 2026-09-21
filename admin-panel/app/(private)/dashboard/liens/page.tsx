"use client";

import { useState } from "react";
import { useGetLiensQuery, useCreateLienMutation, useUpdateLienMutation, useDeleteLienMutation } from "@/store/api/apiSlice";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useToast } from "@/hooks/use-toast";
import { ROLES } from "@/lib/roleConstants";
import { useHasAnyRole } from "@/hooks/usePermissions";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorMessage } from "@/components/error-message";

export default function LiensPage() {
  const { data, isLoading, error } = useGetLiensQuery({});
  const [createLien] = useCreateLienMutation();
  const [updateLien] = useUpdateLienMutation();
  const [deleteLien] = useDeleteLienMutation();
  const { toast } = useToast();
  
  const canManage = useHasAnyRole([ROLES.ADMIN, ROLES.FIRM_ADMIN, ROLES.MEDICAL_BILLER]);

  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [formData, setFormData] = useState({
    case_id: "",
    provider_id: "",
    lien_type: "medical",
    amount: "",
    status: "pending",
    negotiated_amount: "",
    notes: "",
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      if (editingId) {
        await updateLien({ id: editingId, ...formData }).unwrap();
        toast({ title: "Success", description: "Lien updated successfully" });
      } else {
        await createLien(formData).unwrap();
        toast({ title: "Success", description: "Lien created successfully" });
      }
      resetForm();
    } catch (error: any) {
      toast({ title: "Error", description: error.message || "Operation failed", variant: "destructive" });
    }
  };

  const resetForm = () => {
    setFormData({
      case_id: "", provider_id: "", lien_type: "medical", amount: "", status: "pending",
      negotiated_amount: "", notes: "",
    });
    setEditingId(null);
    setShowForm(false);
  };

  const handleEdit = (lien: any) => {
    setFormData({
      case_id: lien.case_id || "",
      provider_id: lien.provider_id || "",
      lien_type: lien.lien_type || "medical",
      amount: lien.amount || "",
      status: lien.status || "pending",
      negotiated_amount: lien.negotiated_amount || "",
      notes: lien.notes || "",
    });
    setEditingId(lien.id);
    setShowForm(true);
  };

  const handleDelete = async (id: number) => {
    if (!confirm("Are you sure you want to delete this lien?")) return;
    try {
      await deleteLien(id).unwrap();
      toast({ title: "Success", description: "Lien deleted successfully" });
    } catch (error: any) {
      toast({ title: "Error", description: error.message || "Delete failed", variant: "destructive" });
    }
  };

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorMessage message="Failed to load liens" />;

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Liens</h1>
        {canManage && (
          <Button onClick={() => { resetForm(); setShowForm(true); }}>
            Add Lien
          </Button>
        )}
      </div>

      {showForm && canManage && (
        <Card className="mb-6">
          <CardHeader>
            <CardTitle>{editingId ? "Edit Lien" : "New Lien"}</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label>Case ID *</label>
                  <Input required value={formData.case_id} onChange={(e) => setFormData({...formData, case_id: e.target.value})} placeholder="Case ID" />
                </div>
                <div>
                  <label>Provider ID</label>
                  <Input value={formData.provider_id} onChange={(e) => setFormData({...formData, provider_id: e.target.value})} placeholder="Provider ID" />
                </div>
                <div>
                  <label>Lien Type *</label>
                  <select value={formData.lien_type} onChange={(e) => setFormData({...formData, lien_type: e.target.value})}>
                    <option value="medical">Medical</option>
                    <option value="attorney">Attorney</option>
                    <option value="government_medicare">Government Medicare</option>
                    <option value="government_medicaid">Government Medicaid</option>
                    <option value="health_insurance">Health Insurance</option>
                  </select>
                </div>
                <div>
                  <label>Amount *</label>
                  <Input required type="number" step="0.01" value={formData.amount} onChange={(e) => setFormData({...formData, amount: e.target.value})} placeholder="Amount" />
                </div>
                <div>
                  <label>Status</label>
                  <select value={formData.status} onChange={(e) => setFormData({...formData, status: e.target.value})}>
                    <option value="pending">Pending</option>
                    <option value="negotiated">Negotiated</option>
                    <option value="settled">Settled</option>
                    <option value="released">Released</option>
                  </select>
                </div>
                <div>
                  <label>Negotiated Amount</label>
                  <Input type="number" step="0.01" value={formData.negotiated_amount} onChange={(e) => setFormData({...formData, negotiated_amount: e.target.value})} placeholder="Negotiated Amount" />
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
                <TableHead>Case ID</TableHead>
                <TableHead>Provider ID</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>Amount</TableHead>
                <TableHead>Status</TableHead>
                {canManage && <TableHead>Actions</TableHead>}
              </TableRow>
            </TableHeader>
            <TableBody>
              {data?.data?.data?.map((lien: any) => (
                <TableRow key={lien.id}>
                  <TableCell>{lien.case_id}</TableCell>
                  <TableCell>{lien.provider_id}</TableCell>
                  <TableCell>{lien.lien_type}</TableCell>
                  <TableCell>${lien.amount}</TableCell>
                  <TableCell>{lien.status}</TableCell>
                  {canManage && (
                    <TableCell>
                      <Button variant="outline" size="sm" onClick={() => handleEdit(lien)}>Edit</Button>
                      <Button variant="destructive" size="sm" onClick={() => handleDelete(lien.id)}>Delete</Button>
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
