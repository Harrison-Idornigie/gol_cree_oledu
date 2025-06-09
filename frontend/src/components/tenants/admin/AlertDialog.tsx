'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/button';

export interface AlertDialogProps {
  trigger: React.ReactNode;
  title: string;
  description: string;
  confirmText: string;
  cancelText: string;
  variant?: 'default' | 'destructive';
  onConfirm: () => void | Promise<void>;
  onCancel?: () => void;
}

export function AlertDialog({
  trigger,
  title,
  description,
  confirmText,
  cancelText,
  variant = 'default',
  onConfirm,
  onCancel,
}: AlertDialogProps) {
  const [isOpen, setIsOpen] = useState(false);

  const handleOpen = () => setIsOpen(true);
  const handleClose = () => {
    setIsOpen(false);
    onCancel?.();
  };

  const handleConfirm = () => {
    onConfirm();
    setIsOpen(false);
  };

  return (
    <>
      <div onClick={handleOpen}>{trigger}</div>
      
      {isOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          <div className="bg-white rounded-lg shadow-lg p-6 max-w-md w-full">
            <div className="mb-4">
              <h3 className="text-lg font-semibold">{title}</h3>
              <p className="text-sm text-gray-500 mt-1">{description}</p>
            </div>
            
            <div className="flex justify-end gap-2">
              <Button 
                variant="outline" 
                onClick={handleClose}
              >
                {cancelText}
              </Button>
              <Button 
                onClick={handleConfirm}
                className={variant === 'destructive' ? 'bg-red-600 hover:bg-red-700 text-white' : ''}
              >
                {confirmText}
              </Button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
