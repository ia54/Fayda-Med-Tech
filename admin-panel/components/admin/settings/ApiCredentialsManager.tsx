'use client';

import React, { useState } from 'react';
import { 
  Card, 
  CardContent, 
  CardDescription, 
  CardHeader, 
  CardTitle 
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { 
  Table, 
  TableBody, 
  TableCell, 
  TableHead, 
  TableHeader, 
  TableRow 
} from '@/components/ui/table';
import { 
  Dialog, 
  DialogContent, 
  DialogDescription, 
  DialogFooter, 
  DialogHeader, 
  DialogTitle, 
  DialogTrigger 
} from '@/components/ui/dialog';
import { 
  useGetApiCredentialsQuery, 
  useCreateApiCredentialMutation, 
  useUpdateApiCredentialMutation, 
  useDeleteApiCredentialMutation,
  ApiCredential
} from '@/store/api/apiCredentialsApiSlice';
import { Key, Plus, Trash2, Edit2, Loader2, Eye, EyeOff } from 'lucide-react';
import { useToast } from '@/hooks/use-toast';

export function ApiCredentialsManager() {
  const { data, isLoading, isError } = useGetApiCredentialsQuery();
  const [createCredential] = useCreateApiCredentialMutation();
  const [updateCredential] = useUpdateApiCredentialMutation();
  const [deleteCredential] = useDeleteApiCredentialMutation();
  const { toast } = useToast();

  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [showKey, setShowKey] = useState<Record<number, boolean>>({});

  const [formData, setFormData] = useState({
    provider: '',
    name: '',
    key: '',
    value: '',
    is_active: true
  });

  const handleOpenDialog = (credential?: ApiCredential) => {
    if (credential) {
      setEditingId(credential.id);
      setFormData({
        provider: credential.provider,
        name: credential.name,
        key: credential.key,
        value: credential.value || '',
        is_active: credential.is_active
      });
    } else {
      setEditingId(null);
      setFormData({
        provider: '',
        name: '',
        key: '',
        value: '',
        is_active: true
      });
    }
    setIsDialogOpen(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      if (editingId) {
        await updateCredential({ id: editingId, data: formData }).unwrap();
        toast({ title: 'Success', description: 'API Credential updated successfully' });
      } else {
        await createCredential(formData).unwrap();
        toast({ title: 'Success', description: 'API Credential created successfully' });
      }
      setIsDialogOpen(false);
    } catch (err) {
      toast({ 
        title: 'Error', 
        description: 'Failed to save credential', 
        variant: 'destructive' 
      });
    }
  };

  const handleDelete = async (id: number) => {
    if (confirm('Are you sure you want to delete this credential?')) {
      try {
        await deleteCredential(id).unwrap();
        toast({ title: 'Success', description: 'API Credential deleted successfully' });
      } catch (err) {
        toast({ 
          title: 'Error', 
          description: 'Failed to delete credential', 
          variant: 'destructive' 
        });
      }
    }
  };

  const toggleKeyVisibility = (id: number) => {
    setShowKey(prev => ({ ...prev, [id]: !prev[id] }));
  };

  if (isLoading) return <div className="flex justify-center p-8"><Loader2 className="animate-spin h-8 w-8" /></div>;

  return (
    <Card id="api-credentials">
      <CardHeader className="flex flex-row items-center justify-between">
        <div>
          <CardTitle>API Credentials</CardTitle>
          <CardDescription>
            Manage sensitive keys for external services (DocuSign, Google Vision, etc.)
          </CardDescription>
        </div>
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button onClick={() => handleOpenDialog()}>
              <Plus className="mr-2 h-4 w-4" /> Add Key
            </Button>
          </DialogTrigger>
          <DialogContent>
            <form onSubmit={handleSubmit}>
              <DialogHeader>
                <DialogTitle>{editingId ? 'Edit Credential' : 'Add New API Credential'}</DialogTitle>
                <DialogDescription>
                  Enter the provider and key details. Keys are stored encrypted in the database.
                </DialogDescription>
              </DialogHeader>
              <div className="space-y-4 py-4">
                <div className="space-y-2">
                  <Label htmlFor="provider">Provider (e.g., docusign, google_vision)</Label>
                  <Input 
                    id="provider" 
                    value={formData.provider} 
                    onChange={e => setFormData({...formData, provider: e.target.value})}
                    placeholder="google_vision"
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="name">Name (e.g., API_KEY, CLIENT_ID)</Label>
                  <Input 
                    id="name" 
                    value={formData.name} 
                    onChange={e => setFormData({...formData, name: e.target.value})}
                    placeholder="API_KEY"
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="key">Key / Token</Label>
                  <Textarea 
                    id="key" 
                    value={formData.key} 
                    onChange={e => setFormData({...formData, key: e.target.value})}
                    placeholder="Enter API key or Private Key content..."
                    className="font-mono text-xs min-h-[120px]"
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="value">Secondary Value (Optional)</Label>
                  <Input 
                    id="value" 
                    type="password"
                    value={formData.value} 
                    onChange={e => setFormData({...formData, value: e.target.value})}
                  />
                </div>
                <div className="flex items-center space-x-2 pt-2">
                  <Switch 
                    id="is_active" 
                    checked={formData.is_active}
                    onCheckedChange={checked => setFormData({...formData, is_active: checked})}
                  />
                  <Label htmlFor="is_active">Active</Label>
                </div>
              </div>
              <DialogFooter>
                <Button type="submit">Save Credential</Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      </CardHeader>
      <CardContent>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Provider</TableHead>
              <TableHead>Name</TableHead>
              <TableHead>Key</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {data?.data.map((item) => (
              <TableRow key={item.id}>
                <TableCell className="font-medium capitalize">{item.provider}</TableCell>
                <TableCell>{item.name}</TableCell>
                <TableCell>
                  <div className="flex items-center space-x-2">
                    <span className="font-mono text-xs">
                      {showKey[item.id] ? item.key : '••••••••••••••••'}
                    </span>
                    <Button 
                      variant="ghost" 
                      size="icon" 
                      className="h-6 w-6" 
                      onClick={() => toggleKeyVisibility(item.id)}
                    >
                      {showKey[item.id] ? <EyeOff className="h-3 w-3" /> : <Eye className="h-3 w-3" />}
                    </Button>
                  </div>
                </TableCell>
                <TableCell>
                  <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${item.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                    {item.is_active ? 'Active' : 'Inactive'}
                  </span>
                </TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end space-x-2">
                    <Button variant="ghost" size="icon" onClick={() => handleOpenDialog(item)}>
                      <Edit2 className="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" className="text-red-600" onClick={() => handleDelete(item.id)}>
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
            {data?.data.length === 0 && (
              <TableRow>
                <TableCell colSpan={5} className="text-center py-4 text-muted-foreground">
                  No API credentials found. Add one to get started.
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  );
}
