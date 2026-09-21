"use client"

import React from "react"
import { useAppDispatch, useAppSelector } from "../../store/hooks"
import { closeModal } from "../../store/slices/modalSlice"
import { Button } from "../ui/button"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "../ui/dialog"
import type { RootState } from "../../store/store"

export function ModalHost() {
  const dispatch = useAppDispatch()
  const { type, props } = useAppSelector((s: RootState) => s.modal)
  const open = type !== "none"

  const onOpenChange = (nextOpen: boolean) => {
    if (!nextOpen) dispatch(closeModal())
  }

  if (!open) return null

  if (type === "confirm") {
    const title = (props?.title as string) ?? "Confirm"
    const message = (props?.message as string) ?? "Are you sure?"
    const onConfirm = props?.onConfirm as (() => void) | undefined
    return (
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-w-md">
          <DialogHeader className="pb-4">
            <DialogTitle className="text-xl font-semibold">{title}</DialogTitle>
          </DialogHeader>
          <div className="py-6">
            <p className="text-sm text-muted-foreground leading-relaxed">{message}</p>
          </div>
          <DialogFooter className="pt-4 gap-3">
            <Button 
              variant="destructive"
              size="lg"
              onClick={() => dispatch(closeModal())}
              className="flex-1 sm:flex-none"
            >
              Cancel
            </Button>
            <Button
              size="lg"
              onClick={() => {
                onConfirm?.()
                dispatch(closeModal())
              }}
              className="flex-1 sm:flex-none"
            >
              Confirm
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    )
  }

  if (type === "custom") {
    const Content = props?.component as React.ComponentType<any>
    return (
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className={props?.className as string | undefined}>
          {Content ? <Content {...props} /> : null}
        </DialogContent>
      </Dialog>
    )
  }

  return null
}